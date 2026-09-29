<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Material;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * اقتراح أفضل مورد لكل مادة من فواتير الشراء المُرحَّلة.
 *
 * المقارنة بين *آخر* سعر اشترينا به من كل مورد، لا بين أرخص سعر في التاريخ:
 * الأسعار تتحرّك، وسعرٌ رخيص من قبل سنة لم يعد عرضاً قائماً. والسعر المقارَن
 * هو الكلفة الفعلية للوحدة — بعد خصم السطر وبعد نصيبها من خصم الفاتورة — لأن
 * مورداً يبيع أغلى ويخصم على الإجمالي قد يكون الأرخص فعلاً.
 *
 * تُستبعد الأسطر المسجّلة بوحدة غير وحدة المادة الحالية: سعر «الكرتونة» لا
 * يُقارن بسعر «القطعة».
 */
class SupplierPriceService
{
    /** كم نعود إلى الوراء؛ ما هو أقدم لا يصلح دليلاً على سعر اليوم. */
    public const LOOKBACK_DAYS = 365;

    /** عدد الموردين المعروضين لكل مادة. */
    public const MAX_SUPPLIERS = 3;

    /**
     * @param  array<int>  $materialIds
     * @return array<int, array<string, mixed>> keyed by material id
     */
    public function suggestions(array $materialIds, ?int $supplierId = null): array
    {
        $materialIds = array_values(array_unique(array_filter(array_map('intval', $materialIds))));
        if ($materialIds === []) {
            return [];
        }

        $units = Material::query()->whereIn('id', $materialIds)->pluck('unit_id', 'id');

        $rows = DB::table('purchase_invoice_items as i')
            ->join('purchase_invoices as p', 'p.id', '=', 'i.purchase_invoice_id')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->whereIn('i.material_id', $materialIds)
            ->where('p.status', DocumentStatus::POSTED->value)
            ->whereDate('p.purchase_date', '>=', now()->subDays(self::LOOKBACK_DAYS)->toDateString())
            ->where('i.quantity', '>', 0)
            ->orderByDesc('p.purchase_date')
            ->orderByDesc('p.id')
            ->get([
                'i.material_id', 'i.unit_id', 'i.quantity', 'i.line_total',
                'p.id as invoice_id', 'p.invoice_no', 'p.purchase_date', 'p.subtotal', 'p.total',
                's.id as supplier_id', 's.name as supplier_name',
            ]);

        $result = [];

        foreach ($rows->groupBy('material_id') as $materialId => $lines) {
            $unitId = $units[$materialId] ?? null;

            // الصفوف مرتّبة من الأحدث، فأوّل سطر لكل مورد هو آخر سعر منه.
            $latest = $lines
                ->filter(fn ($l) => $unitId === null || (int) $l->unit_id === (int) $unitId)
                ->unique('supplier_id')
                ->map(fn ($l) => [
                    'supplier_id' => (int) $l->supplier_id,
                    'supplier_name' => $l->supplier_name,
                    'unit_cost' => $this->effectiveUnitCost($l),
                    'purchase_date' => substr((string) $l->purchase_date, 0, 10),
                    'invoice_id' => (int) $l->invoice_id,
                    'invoice_no' => $l->invoice_no,
                ])
                ->sortBy([['unit_cost', 'asc'], ['purchase_date', 'desc']])
                ->values();

            if ($latest->isEmpty()) {
                continue;
            }

            $result[(int) $materialId] = [
                'material_id' => (int) $materialId,
                'best' => $this->format($latest->first()),
                'selected_supplier' => $supplierId
                    ? $this->format($latest->firstWhere('supplier_id', $supplierId))
                    : null,
                'suppliers' => $latest->take(self::MAX_SUPPLIERS)->map(fn ($s) => $this->format($s))->all(),
            ];
        }

        return $result;
    }

    /** كلفة الوحدة بعد خصم السطر ونصيبها النسبي من خصم الفاتورة. */
    private function effectiveUnitCost(object $line): float
    {
        $unit = (float) $line->line_total / (float) $line->quantity;
        $subtotal = (float) $line->subtotal;

        if ($subtotal > 0) {
            $unit *= (float) $line->total / $subtotal;
        }

        return round(max(0, $unit), 2);
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array<string, mixed>|null
     */
    private function format(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return ['unit_cost' => number_format($row['unit_cost'], 2, '.', '')] + $row;
    }
}
