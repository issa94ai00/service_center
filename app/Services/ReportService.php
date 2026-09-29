<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\Repair;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Technician;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reporting queries.
 *
 * Every figure is aggregated in SQL rather than by pulling rows into PHP, so a
 * report over a year of invoices costs the same as one over a day. Money is
 * normalised through Money on the way out so the API never emits a bare float.
 */
class ReportService
{
    public const MAX_PER_PAGE = 200;

    public const DEFAULT_PER_PAGE = 50;

    /**
     * Materials at or below their minimum stock level, scarcest first.
     *
     * @param  array{search?: ?string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function lowStock(array $filters = []): Collection
    {
        return Material::query()
            ->with('unit:id,name_ar,name_en')
            ->where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock')
            ->when(
                $filters['search'] ?? null,
                fn (Builder $q, string $search) => $q->where(function (Builder $inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                })
            )
            ->orderByRaw('(stock_quantity - min_stock) asc')
            ->orderBy('name')
            ->get()
            ->map(fn (Material $material) => [
                'id' => $material->id,
                'name' => $material->name,
                'sku' => $material->sku,
                'unit' => $material->unit?->name_ar,
                'stock_quantity' => $material->stock_quantity,
                'min_stock' => $material->min_stock,
                'shortfall' => $this->quantity(
                    (float) $material->min_stock - (float) $material->stock_quantity
                ),
                'cost_price' => Money::fromDecimal($material->cost_price)->toDecimal(),
                'sale_price' => Money::fromDecimal($material->sale_price)->toDecimal(),
                'is_low' => $material->is_low,
                'is_out_of_stock' => (float) $material->stock_quantity <= 0,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function salesSummary(string $from, string $to): array
    {
        return $this->documentSummary(
            fn (): Builder => SalesInvoice::query()->where('status', DocumentStatus::POSTED),
            'invoice_date',
            $from,
            $to,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function purchasesSummary(string $from, string $to): array
    {
        return $this->documentSummary(
            fn (): Builder => PurchaseInvoice::query()->where('status', DocumentStatus::POSTED),
            'purchase_date',
            $from,
            $to,
        );
    }

    /**
     * Outstanding customer balances — what the company is still owed.
     *
     * @param  array{as_of?: ?string, only_outstanding?: bool, search?: ?string, per_page?: ?int}  $filters
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, string>, paginator: LengthAwarePaginator}
     */
    public function receivables(array $filters = []): array
    {
        return $this->outstanding(
            model: Customer::class,
            table: 'customers',
            transactionsTable: 'customer_transactions',
            foreignKey: 'customer_id',
            filters: $filters,
        );
    }

    /**
     * Outstanding supplier balances. A supplier ledger runs the other way
     * round: a negative balance means the company still owes the supplier,
     * so the sign is flipped here to keep the report readable.
     *
     * @param  array{as_of?: ?string, only_outstanding?: bool, search?: ?string, per_page?: ?int}  $filters
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, string>, paginator: LengthAwarePaginator}
     */
    public function payables(array $filters = []): array
    {
        return $this->outstanding(
            model: Supplier::class,
            table: 'suppliers',
            transactionsTable: 'supplier_transactions',
            foreignKey: 'supplier_id',
            filters: $filters,
            invertSign: true,
        );
    }

    /**
     * محاسبة خبراء الصيانة: صف لكل خبير.
     *
     * يجمع بين ما تحقّق في الفترة (بلاغات، إيراد، عمولة، مصروف) وبين الرصيد
     * القائم حتى نهايتها. الرصيد مقلوب الإشارة مثل تقرير الموردين، فالموجب
     * يعني أن المحل ما زال مديناً للخبير.
     *
     * @param  array{from?: ?string, to?: ?string, search?: ?string, only_active?: bool, per_page?: ?int}  $filters
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, string>, paginator: LengthAwarePaginator}
     */
    public function technicianSummary(array $filters = []): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $perPage = $this->perPage($filters);

        // العمولة المستحقة في الفترة: صافي ما قُيِّد في دفتره مقابل بلاغات،
        // فالقيود العكسية لبلاغ أُعيدت تسويته تُخصم منها تلقائياً.
        [$commission, $commissionBindings] = $this->technicianLedger('t.credit - t.debit', Repair::class, $from, $to);
        // المصروف في الفترة: صافي سندات الصرف، ناقص ما أُلغي منها.
        [$paid, $paidBindings] = $this->technicianLedger('t.debit - t.credit', Payment::class, $from, $to);
        // الرصيد يتجاهل `from`: رصيد قائم حتى نهاية الفترة، لا حركة داخلها.
        [$ledger, $ledgerBindings] = $this->technicianLedger('t.debit - t.credit', null, null, $to);

        $balance = "(-1 * (technicians.opening_balance + {$ledger}))";

        [$repairs, $repairsBindings] = $this->technicianRepairsAggregate('COUNT(*)', $from, $to);
        [$revenue, $revenueBindings] = $this->technicianRepairsAggregate('SUM(r.final_cost)', $from, $to);

        $base = fn (): Builder => Technician::query()
            ->select('technicians.*')
            ->selectRaw("{$repairs} as repairs_count", $repairsBindings)
            ->selectRaw("{$revenue} as revenue", $revenueBindings)
            ->selectRaw("{$commission} as commission", $commissionBindings)
            ->selectRaw("{$paid} as paid", $paidBindings)
            ->selectRaw("{$balance} as balance", $ledgerBindings)
            ->when(
                $filters['search'] ?? null,
                fn (Builder $q, string $search) => $q->where(function (Builder $inner) use ($search) {
                    $inner->where('technicians.name', 'like', "%{$search}%")
                        ->orWhere('technicians.specialty', 'like', "%{$search}%")
                        ->orWhere('technicians.phone', 'like', "%{$search}%");
                })
            )
            ->when($filters['only_active'] ?? false, fn (Builder $q) => $q->where('technicians.is_active', true));

        // المجاميع تغطّي كل الخبراء المطابقين، لا صفحة واحدة منهم.
        $totals = DB::query()
            ->fromSub($base()->reorder(), 'technicians_summary')
            ->selectRaw('COUNT(*) as technicians')
            ->selectRaw('COALESCE(SUM(repairs_count), 0) as repairs_count')
            ->selectRaw('COALESCE(SUM(revenue), 0) as revenue')
            ->selectRaw('COALESCE(SUM(commission), 0) as commission')
            ->selectRaw('COALESCE(SUM(paid), 0) as paid')
            ->selectRaw('COALESCE(SUM(balance), 0) as balance')
            ->first();

        /** @var LengthAwarePaginator $paginator */
        $paginator = $base()
            ->orderByRaw("{$commission} desc", $commissionBindings)
            ->orderBy('technicians.name')
            ->paginate($perPage);

        $rows = $paginator->getCollection()->map(fn (Technician $technician) => [
            'id' => $technician->id,
            'name' => $technician->name,
            'specialty' => $technician->specialty,
            'phone' => $technician->phone,
            'commission_rate' => Money::fromDecimal($technician->commission_rate)->toDecimal(),
            'is_active' => (bool) $technician->is_active,
            'repairs_count' => (int) $technician->repairs_count,
            'revenue' => Money::fromDecimal($technician->revenue)->toDecimal(),
            'commission' => Money::fromDecimal($technician->commission)->toDecimal(),
            'paid' => Money::fromDecimal($technician->paid)->toDecimal(),
            'balance' => Money::fromDecimal($technician->balance)->toDecimal(),
        ]);

        return [
            'rows' => $rows,
            'totals' => [
                'technicians' => (string) (int) ($totals?->technicians ?? 0),
                'repairs_count' => (string) (int) ($totals?->repairs_count ?? 0),
                'revenue' => Money::fromDecimal($totals?->revenue)->toDecimal(),
                'commission' => Money::fromDecimal($totals?->commission)->toDecimal(),
                'paid' => Money::fromDecimal($totals?->paid)->toDecimal(),
                'balance' => Money::fromDecimal($totals?->balance)->toDecimal(),
            ],
            'paginator' => $paginator,
        ];
    }

    /**
     * تفصيل بلاغ بلاغ: يراجع به المحاسبُ احتسابَ العمولة بلاغاً بلاغاً.
     *
     * @param  array{technician_id?: ?int, from?: ?string, to?: ?string, per_page?: ?int}  $filters
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, string>, paginator: LengthAwarePaginator}
     */
    public function technicianRepairs(array $filters = []): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $perPage = $this->perPage($filters);

        $base = fn (): Builder => Repair::query()
            ->with(['customer:id,name', 'technician:id,name'])
            ->whereNotNull('repairs.technician_id')
            // البلاغ يدخل المحاسبة لحظة تسويته، لا لحظة استلامه.
            ->whereNotNull('repairs.settled_at')
            ->when($filters['technician_id'] ?? null, fn (Builder $q, $id) => $q->where('repairs.technician_id', $id))
            ->when($from, fn (Builder $q) => $q->where('repairs.settled_at', '>=', DateRange::start($from)))
            ->when($to, fn (Builder $q) => $q->where('repairs.settled_at', '<=', DateRange::end($to)));

        $totals = $base()->reorder()
            ->selectRaw('COUNT(*) as repairs_count')
            ->selectRaw('COALESCE(SUM(repairs.final_cost), 0) as revenue')
            ->selectRaw('COALESCE(SUM(repairs.commission_amount), 0) as commission')
            ->first();

        /** @var LengthAwarePaginator $paginator */
        $paginator = $base()
            ->orderByDesc('repairs.settled_at')
            ->orderByDesc('repairs.id')
            ->paginate($perPage);

        $rows = $paginator->getCollection()->map(fn (Repair $repair) => [
            'id' => $repair->id,
            'receipt_no' => $repair->receipt_no,
            'settled_at' => $repair->settled_at?->format('Y-m-d'),
            'technician_id' => $repair->technician_id,
            'technician' => $repair->technician?->name,
            'customer' => $repair->customer?->name,
            'device' => collect([$repair->brand, $repair->model])->filter()->join(' ') ?: $repair->device_type,
            'final_cost' => Money::fromDecimal($repair->final_cost)->toDecimal(),
            'commission_rate' => Money::fromDecimal($repair->commission_rate)->toDecimal(),
            'commission_amount' => Money::fromDecimal($repair->commission_amount)->toDecimal(),
        ]);

        return [
            'rows' => $rows,
            'totals' => [
                'repairs_count' => (string) (int) ($totals?->repairs_count ?? 0),
                'revenue' => Money::fromDecimal($totals?->revenue)->toDecimal(),
                'commission' => Money::fromDecimal($totals?->commission)->toDecimal(),
            ],
            'paginator' => $paginator,
        ];
    }

    /**
     * مجموع فرعي مرتبط على دفتر الخبير.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    protected function technicianLedger(string $expression, ?string $referenceType, ?string $from, ?string $to): array
    {
        $sql = "COALESCE((SELECT SUM({$expression}) FROM technician_transactions t WHERE t.technician_id = technicians.id";
        $bindings = [];

        if ($referenceType !== null) {
            $sql .= ' AND t.reference_type = ?';
            $bindings[] = $referenceType;
        }

        if ($from) {
            $sql .= ' AND t.transaction_date >= ?';
            $bindings[] = DateRange::start($from);
        }

        if ($to) {
            $sql .= ' AND t.transaction_date <= ?';
            $bindings[] = DateRange::end($to);
        }

        return [$sql.'), 0)', $bindings];
    }

    /**
     * مجموع فرعي مرتبط على بلاغات الخبير المسوّاة داخل الفترة.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    protected function technicianRepairsAggregate(string $expression, ?string $from, ?string $to): array
    {
        $sql = "COALESCE((SELECT {$expression} FROM repairs r WHERE r.technician_id = technicians.id"
            .' AND r.settled_at IS NOT NULL';
        $bindings = [];

        if ($from) {
            $sql .= ' AND r.settled_at >= ?';
            $bindings[] = DateRange::start($from);
        }

        if ($to) {
            $sql .= ' AND r.settled_at <= ?';
            $bindings[] = DateRange::end($to);
        }

        return [$sql.'), 0)', $bindings];
    }

    /**
     * Stock movement history. Filtering to a single material adds a running
     * quantity so the report reconstructs how that material reached its
     * current stock level.
     *
     * @param  array{material_id?: ?int, type?: ?string, from?: ?string, to?: ?string, per_page?: ?int}  $filters
     * @return array{rows: array<int, array<string, mixed>>, running: bool, paginator: LengthAwarePaginator}
     */
    public function stockMovements(array $filters = []): array
    {
        $materialId = $filters['material_id'] ?? null;
        $perPage = $this->perPage($filters);

        $base = fn (): Builder => StockMovement::query()
            ->when($materialId, fn (Builder $q, $id) => $q->where('material_id', $id))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->where('movement_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->where('movement_date', '<=', DateRange::end($to)));

        // Oldest first when we are accumulating a running quantity, newest
        // first otherwise — a plain movement log reads better in reverse.
        $ascending = (bool) $materialId;

        /** @var LengthAwarePaginator $paginator */
        $paginator = $base()
            ->with(['material:id,name,sku,unit_id', 'material.unit:id,name_ar', 'creator:id,name'])
            ->orderBy('movement_date', $ascending ? 'asc' : 'desc')
            ->orderBy('id', $ascending ? 'asc' : 'desc')
            ->paginate($perPage);

        $running = $ascending
            ? (float) $this->quantityBefore($base, $paginator->getCollection()->first())
            : null;

        $rows = [];
        foreach ($paginator->getCollection() as $movement) {
            $signed = $this->signedQuantity($movement);

            if ($running !== null) {
                $running += (float) $signed;
            }

            $rows[] = [
                'id' => $movement->id,
                'movement_date' => $movement->movement_date?->format('Y-m-d'),
                'material' => [
                    'id' => $movement->material_id,
                    'name' => $movement->material?->name,
                    'sku' => $movement->material?->sku,
                    'unit' => $movement->material?->unit?->name_ar,
                ],
                'type' => $movement->type->value,
                'type_label' => $this->movementLabel($movement->type->value),
                'direction' => $movement->direction->value,
                'quantity' => $this->quantity($movement->quantity),
                'signed_quantity' => $signed,
                'running_quantity' => $running === null ? null : $this->quantity($running),
                'unit_price' => Money::fromDecimal($movement->unit_price)->toDecimal(),
                'source_type' => $movement->source_type->value,
                'source_id' => $movement->source_id,
                'reference_no' => $movement->reference_no,
                'description' => $movement->description,
                'created_by' => $movement->creator?->name,
                'is_reversal' => $movement->cancels_id !== null,
            ];
        }

        return [
            'rows' => $rows,
            'running' => $running !== null,
            'paginator' => $paginator,
        ];
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  callable(): Builder  $baseQuery
     * @return array<string, mixed>
     */
    protected function documentSummary(callable $baseQuery, string $dateColumn, string $from, string $to): array
    {
        $scoped = fn (): Builder => $baseQuery()->whereBetween($dateColumn, DateRange::between($from, $to));

        $totals = $scoped()
            ->selectRaw('COUNT(*) as documents')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as subtotal')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discount')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->selectRaw('COALESCE(SUM(paid_amount), 0) as paid')
            ->selectRaw('COALESCE(SUM(remaining), 0) as outstanding')
            ->first();

        $byDay = $scoped()
            ->selectRaw("{$dateColumn} as date")
            ->selectRaw('COUNT(*) as documents')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->selectRaw('COALESCE(SUM(paid_amount), 0) as paid')
            ->selectRaw('COALESCE(SUM(remaining), 0) as outstanding')
            ->groupBy($dateColumn)
            ->orderBy($dateColumn)
            ->get()
            ->map(fn ($row) => [
                // The grouped column bypasses the model's date cast, so it can
                // arrive as a raw datetime string depending on the driver.
                'date' => substr((string) $row->date, 0, 10),
                'count' => (int) $row->documents,
                'total' => Money::fromDecimal($row->total)->toDecimal(),
                'paid' => Money::fromDecimal($row->paid)->toDecimal(),
                'outstanding' => Money::fromDecimal($row->outstanding)->toDecimal(),
            ]);

        return [
            'from' => $from,
            'to' => $to,
            'count' => (int) ($totals?->documents ?? 0),
            'subtotal' => Money::fromDecimal($totals?->subtotal)->toDecimal(),
            'discount' => Money::fromDecimal($totals?->discount)->toDecimal(),
            'total' => Money::fromDecimal($totals?->total)->toDecimal(),
            'paid' => Money::fromDecimal($totals?->paid)->toDecimal(),
            'outstanding' => Money::fromDecimal($totals?->outstanding)->toDecimal(),
            'by_day' => $byDay,
        ];
    }

    /**
     * Shared receivables/payables aggregation.
     *
     * The balance is the party's opening balance plus the net of its ledger,
     * computed as a correlated subquery so it can be filtered and totalled in
     * SQL instead of loading every party into memory.
     *
     * @param  class-string  $model
     * @param  array<string, mixed>  $filters
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, string>, paginator: LengthAwarePaginator}
     */
    protected function outstanding(
        string $model,
        string $table,
        string $transactionsTable,
        string $foreignKey,
        array $filters,
        bool $invertSign = false,
    ): array {
        $asOf = $filters['as_of'] ?? null;
        $onlyOutstanding = $filters['only_outstanding'] ?? true;
        $perPage = $this->perPage($filters);

        $sign = $invertSign ? '-1 * ' : '';
        $ledger = "COALESCE((SELECT SUM(t.debit - t.credit) FROM {$transactionsTable} t"
            ." WHERE t.{$foreignKey} = {$table}.id"
            .($asOf ? ' AND t.transaction_date <= ?' : '')
            .'), 0)';

        $balanceExpression = "({$sign}({$table}.opening_balance + {$ledger}))";

        $bindings = $asOf ? [DateRange::end($asOf)] : [];

        $base = fn (): Builder => $model::query()
            ->select("{$table}.*")
            ->selectRaw("{$balanceExpression} as balance", $bindings)
            ->when(
                $filters['search'] ?? null,
                fn (Builder $q, string $search) => $q->where(function (Builder $inner) use ($search, $table) {
                    $inner->where("{$table}.name", 'like', "%{$search}%")
                        ->orWhere("{$table}.company", 'like', "%{$search}%")
                        ->orWhere("{$table}.phone", 'like', "%{$search}%");
                })
            )
            ->when($onlyOutstanding, fn (Builder $q) => $q->whereRaw("{$balanceExpression} > 0", $bindings));

        // Totals cover every matching party, not just the current page.
        $totals = DB::query()
            ->fromSub($base()->reorder(), 'parties')
            ->selectRaw('COUNT(*) as parties')
            ->selectRaw('COALESCE(SUM(balance), 0) as balance')
            ->first();

        /** @var LengthAwarePaginator $paginator */
        $paginator = $base()
            ->orderByRaw("{$balanceExpression} desc", $bindings)
            ->orderBy("{$table}.name")
            ->paginate($perPage);

        $rows = $paginator->getCollection()->map(fn ($party) => [
            'id' => $party->id,
            'name' => $party->name,
            'company' => $party->company,
            'phone' => $party->phone,
            'opening_balance' => Money::fromDecimal($party->opening_balance)->toDecimal(),
            'balance' => Money::fromDecimal($party->balance)->toDecimal(),
        ]);

        return [
            'rows' => $rows,
            'totals' => [
                'parties' => (string) (int) ($totals?->parties ?? 0),
                'balance' => Money::fromDecimal($totals?->balance)->toDecimal(),
            ],
            'paginator' => $paginator,
        ];
    }

    /**
     * Net signed quantity of every movement preceding `$first` in report order.
     *
     * @param  callable(): Builder  $baseQuery
     */
    protected function quantityBefore(callable $baseQuery, ?StockMovement $first): string
    {
        if ($first === null) {
            return '0';
        }

        $date = $first->movement_date->format('Y-m-d');

        $net = $baseQuery()
            ->where(function (Builder $q) use ($date, $first) {
                $q->where('movement_date', '<', $date)
                    ->orWhere(function (Builder $inner) use ($date, $first) {
                        $inner->where('movement_date', '=', $date)->where('id', '<', $first->id);
                    });
            })
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END), 0) as net")
            ->value('net');

        return (string) ($net ?? 0);
    }

    protected function signedQuantity(StockMovement $movement): string
    {
        $quantity = (float) $movement->quantity;

        return $this->quantity($movement->direction->value === 'IN' ? $quantity : -$quantity);
    }

    /** Quantities carry 3 decimals throughout the schema. */
    protected function quantity(float|string $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function perPage(array $filters): int
    {
        return min(max((int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE), 1), self::MAX_PER_PAGE);
    }

    protected function movementLabel(string $type): string
    {
        return match ($type) {
            'PURCHASE' => 'شراء',
            'SALE' => 'بيع',
            'ADJUSTMENT' => 'تسوية مخزون',
            'PURCHASE_RETURN' => 'مرتجع شراء',
            'SALE_RETURN' => 'مرتجع بيع',
            'OPENING' => 'رصيد افتتاحي',
            default => $type,
        };
    }
}
