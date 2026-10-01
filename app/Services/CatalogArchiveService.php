<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Material;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Archiving (soft-deleting) catalogue items, and the rules around it.
 *
 * Archive is the everyday "delete": the item leaves lists, pickers and the
 * barcode scanner, but every invoice, stock movement and report keeps naming
 * it, and it can be restored. Permanent deletion is only for an archived item
 * nothing ever referred to — typically one created by mistake.
 */
class CatalogArchiveService
{
    public function archive(Material|Service $item): void
    {
        if ($item instanceof Material && (float) $item->stock_quantity != 0.0) {
            throw new BusinessRuleException(__(
                'لا يمكن أرشفة «:name» وفي المخزون منها :qty — صفّر الرصيد بتسوية مخزنية أولاً، حتى لا تختفي قيمتها من الجرد.',
                ['name' => $item->name, 'qty' => rtrim(rtrim((string) $item->stock_quantity, '0'), '.')],
            ));
        }

        $drafts = $this->draftInvoiceNumbers($item);

        if ($drafts !== []) {
            throw new BusinessRuleException(__(
                '«:name» مستخدمة في فواتير مسودّة لم تُرحَّل بعد (:list) — رحّلها أو احذف البند منها ثم أعد المحاولة.',
                ['name' => $item->name, 'list' => implode('، ', $drafts)],
            ));
        }

        $item->delete();
    }

    public function restore(Material|Service $item): void
    {
        $item->restore();
    }

    public function forceDelete(Material|Service $item): void
    {
        if (! $item->trashed()) {
            throw new BusinessRuleException(__('أرشِف العنصر أولاً؛ الحذف النهائي متاح من الأرشيف فقط.'));
        }

        if ($this->isReferenced($item)) {
            throw new BusinessRuleException(__(
                '«:name» لها فواتير أو حركات مخزنية، فتبقى في الأرشيف ليظل سجلها مقروءاً.',
                ['name' => $item->name],
            ));
        }

        $item->forceDelete();
    }

    /** Has anything ever been recorded against this item? */
    public function isReferenced(Material|Service $item): bool
    {
        return $item instanceof Material
            ? DB::table('stock_movements')->where('material_id', $item->id)->exists()
                || DB::table('sales_invoice_items')->where('material_id', $item->id)->exists()
                || DB::table('purchase_invoice_items')->where('material_id', $item->id)->exists()
                || DB::table('inventory_adjustment_items')->where('material_id', $item->id)->exists()
            : DB::table('sales_invoice_items')->where('service_id', $item->id)->exists();
    }

    /**
     * Adds an `is_referenced` column to a catalogue query, so an archive list
     * can tell which rows may still be deleted for good without a query per row.
     *
     * @param  class-string<Model>  $model
     */
    public function withReferenceFlag(Builder $query, string $model): Builder
    {
        $id = $query->getModel()->getQualifiedKeyName();

        $tables = $model === Material::class
            ? ['stock_movements', 'sales_invoice_items', 'purchase_invoice_items', 'inventory_adjustment_items']
            : ['sales_invoice_items'];
        $column = $model === Material::class ? 'material_id' : 'service_id';

        $checks = array_map(
            fn (string $table) => "EXISTS (SELECT 1 FROM {$table} WHERE {$table}.{$column} = {$id})",
            $tables,
        );

        return $query->addSelect($query->getModel()->getTable().'.*')
            ->selectRaw('('.implode(' OR ', $checks).') AS is_referenced');
    }

    /**
     * Unposted invoices with a line on this item. Archiving under them would
     * leave a draft that can no longer be posted.
     *
     * @return array<int, string>
     */
    protected function draftInvoiceNumbers(Material|Service $item): array
    {
        $column = $item instanceof Material ? 'material_id' : 'service_id';

        $sales = DB::table('sales_invoices')
            ->join('sales_invoice_items', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->where('sales_invoices.status', 'DRAFT')
            ->where("sales_invoice_items.{$column}", $item->id)
            ->distinct()
            ->limit(5)
            ->pluck('sales_invoices.invoice_no')
            ->all();

        $purchases = $item instanceof Material
            ? DB::table('purchase_invoices')
                ->join('purchase_invoice_items', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                ->where('purchase_invoices.status', 'DRAFT')
                ->where('purchase_invoice_items.material_id', $item->id)
                ->distinct()
                ->limit(5)
                ->pluck('purchase_invoices.invoice_no')
                ->all()
            : [];

        // A draft may not be numbered yet; name it as such rather than blank.
        return array_map(fn ($no) => $no ?: 'مسودّة بلا رقم', [...$sales, ...$purchases]);
    }
}
