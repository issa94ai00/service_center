<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\Repair;
use App\Models\SalesInvoice;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\Technician;
use App\Models\TechnicianTransaction;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds customer/supplier ledger statements.
 *
 * The ledger is append-only: every posted document writes a row into
 * customer_transactions / supplier_transactions, and a statement is a pure
 * read over those rows. Nothing here mutates state.
 *
 * Balances are always derived on the server (never in Vue) and every
 * arithmetic step goes through Money so no float drift can reach a balance.
 */
class StatementService
{
    /** Maximum rows a single page may request. */
    public const MAX_PER_PAGE = 200;

    public const DEFAULT_PER_PAGE = 50;

    /**
     * Which column carries the human-readable document number per reference type.
     *
     * @var array<class-string, string>
     */
    protected const REFERENCE_NUMBER_COLUMNS = [
        SalesInvoice::class => 'invoice_no',
        PurchaseInvoice::class => 'invoice_no',
        Payment::class => 'reference_no',
        // بلاغ الصيانة يشتقّ رقمه من معرّفه ولا يخزّنه عموداً، فيُركَّب أدناه.
        Repair::class => 'id',
    ];

    /**
     * Short, route-friendly keys so the frontend can deep-link a ledger row
     * back to the document that produced it.
     *
     * @var array<class-string, string>
     */
    protected const REFERENCE_KEYS = [
        SalesInvoice::class => 'sales_invoice',
        PurchaseInvoice::class => 'purchase_invoice',
        Payment::class => 'payment',
        Repair::class => 'repair',
    ];

    /**
     * @param  array{from?: ?string, to?: ?string, per_page?: ?int}  $filters
     * @return array{statement: array<string, mixed>, paginator: LengthAwarePaginator}
     */
    public function forCustomer(Customer $customer, array $filters = []): array
    {
        return $this->build(
            party: [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'company' => $customer->company,
            ],
            openingBalance: $customer->opening_balance,
            baseQuery: fn (): Builder => CustomerTransaction::query()->where('customer_id', $customer->id),
            filters: $filters,
        );
    }

    /**
     * @param  array{from?: ?string, to?: ?string, per_page?: ?int}  $filters
     * @return array{statement: array<string, mixed>, paginator: LengthAwarePaginator}
     */
    public function forSupplier(Supplier $supplier, array $filters = []): array
    {
        return $this->build(
            party: [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'company' => $supplier->company,
            ],
            openingBalance: $supplier->opening_balance,
            baseQuery: fn (): Builder => SupplierTransaction::query()->where('supplier_id', $supplier->id),
            filters: $filters,
        );
    }

    /**
     * كشف حساب خبير صيانة: عمولاته دائنة ودفعاته مدينة، بنفس اصطلاح المورد.
     *
     * @param  array{from?: ?string, to?: ?string, per_page?: ?int}  $filters
     * @return array{statement: array<string, mixed>, paginator: LengthAwarePaginator}
     */
    public function forTechnician(Technician $technician, array $filters = []): array
    {
        return $this->build(
            party: [
                'id' => $technician->id,
                'name' => $technician->name,
                'phone' => $technician->phone,
                'company' => $technician->specialty,
            ],
            openingBalance: $technician->opening_balance,
            baseQuery: fn (): Builder => TechnicianTransaction::query()->where('technician_id', $technician->id),
            filters: $filters,
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $party
     * @param  callable(): Builder  $baseQuery
     * @param  array{from?: ?string, to?: ?string, per_page?: ?int}  $filters
     * @return array{statement: array<string, mixed>, paginator: LengthAwarePaginator}
     */
    protected function build(array $party, $openingBalance, callable $baseQuery, array $filters): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        // The HTTP layer validates per_page against MAX_PER_PAGE; only internal
        // callers that render a whole period at once (the PDF export) raise the
        // ceiling, and they pass it explicitly.
        $ceiling = max((int) ($filters['max_per_page'] ?? self::MAX_PER_PAGE), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE), 1), $ceiling);

        // The party's own opening balance, before any ledger row exists.
        $ledgerOpening = Money::fromDecimal($openingBalance);

        // Period opening = ledger opening + net of everything strictly before `from`.
        // Plain `where` (not `whereDate`) so the composite index stays usable.
        $periodOpening = $from
            ? $ledgerOpening->add(Money::fromDecimal(
                $baseQuery()->where('transaction_date', '<', $from)->sum(
                    DB::raw('debit - credit')
                )
            ))
            : $ledgerOpening;

        // Period totals are computed over the whole filtered range, not just the
        // current page, so the summary cards stay correct while paginating.
        $totals = $this->periodQuery($baseQuery, $from, $to)
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $totalDebit = Money::fromDecimal($totals?->total_debit);
        $totalCredit = Money::fromDecimal($totals?->total_credit);
        $closing = $periodOpening->add($totalDebit)->subtract($totalCredit);

        /** @var LengthAwarePaginator $paginator */
        $paginator = $this->periodQuery($baseQuery, $from, $to)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate($perPage);

        /** @var EloquentCollection $transactions */
        $transactions = $paginator->getCollection();

        // Seed the running balance for this page from everything that precedes
        // its first row in ledger order, so page N shows the same balances it
        // would show if the whole ledger had been rendered at once.
        $running = $this->pageOpeningBalance($baseQuery, $ledgerOpening, $transactions->first());
        $pageOpening = $running;

        $referenceNumbers = $this->referenceNumbers($transactions);

        $rows = [];
        foreach ($transactions as $txn) {
            $running = $running
                ->add(Money::fromDecimal($txn->debit))
                ->subtract(Money::fromDecimal($txn->credit));

            $rows[] = $this->row($txn, $running, $referenceNumbers);
        }

        return [
            'statement' => [
                'party' => $party,
                'period' => ['from' => $from, 'to' => $to],
                'opening_balance' => $periodOpening->toDecimal(),
                'page_opening_balance' => $pageOpening->toDecimal(),
                'totals' => [
                    'debit' => $totalDebit->toDecimal(),
                    'credit' => $totalCredit->toDecimal(),
                ],
                'closing_balance' => $closing->toDecimal(),
                'rows' => $rows,
            ],
            'paginator' => $paginator,
        ];
    }

    /**
     * @param  callable(): Builder  $baseQuery
     */
    protected function periodQuery(callable $baseQuery, ?string $from, ?string $to): Builder
    {
        return $baseQuery()
            ->when($from, fn (Builder $q) => $q->where('transaction_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('transaction_date', '<=', DateRange::end($to)));
    }

    /**
     * Net of every ledger row that sorts before `$first` under (transaction_date, id).
     *
     * @param  callable(): Builder  $baseQuery
     */
    protected function pageOpeningBalance(callable $baseQuery, Money $ledgerOpening, $first): Money
    {
        if ($first === null) {
            return $ledgerOpening;
        }

        $date = $first->transaction_date->format('Y-m-d');

        $net = $baseQuery()
            ->where(function (Builder $q) use ($date, $first) {
                $q->where('transaction_date', '<', $date)
                    ->orWhere(function (Builder $q2) use ($date, $first) {
                        $q2->where('transaction_date', '=', $date)->where('id', '<', $first->id);
                    });
            })
            ->sum(DB::raw('debit - credit'));

        return $ledgerOpening->add(Money::fromDecimal($net));
    }

    /**
     * Resolve every referenced document number in one query per reference type
     * instead of one (or two) per ledger row.
     *
     * @return Collection<string, Collection<int, string|null>>
     */
    protected function referenceNumbers(EloquentCollection $transactions): Collection
    {
        return $transactions
            ->filter(fn ($txn) => $txn->reference_type && $txn->reference_id)
            ->groupBy('reference_type')
            ->map(function (Collection $group, string $type): Collection {
                $column = self::REFERENCE_NUMBER_COLUMNS[$type] ?? null;

                if ($column === null) {
                    return collect();
                }

                $numbers = $type::query()
                    ->whereIn('id', $group->pluck('reference_id')->unique())
                    ->pluck($column, 'id');

                return $type === Repair::class
                    ? $numbers->map(fn ($id) => Setting::get('repair_receipt_prefix', 'RPR-').$id)
                    : $numbers;
            });
    }

    /**
     * @param  Collection<string, Collection<int, string|null>>  $referenceNumbers
     * @return array<string, mixed>
     */
    protected function row($txn, Money $running, Collection $referenceNumbers): array
    {
        $type = $txn->reference_type;

        return [
            'id' => $txn->id,
            'transaction_date' => $txn->transaction_date?->format('Y-m-d'),
            'type' => $txn->type->value,
            'type_label' => $this->typeLabel($txn->type),
            'reference_no' => $type ? ($referenceNumbers->get($type)?->get($txn->reference_id)) : null,
            'reference' => $type && isset(self::REFERENCE_KEYS[$type])
                ? ['kind' => self::REFERENCE_KEYS[$type], 'id' => $txn->reference_id]
                : null,
            'description' => $txn->description,
            'debit' => $txn->debit,
            'credit' => $txn->credit,
            'running_balance' => $running->toDecimal(),
        ];
    }

    protected function typeLabel(TransactionType $type): string
    {
        return match ($type) {
            TransactionType::SALES_INVOICE => 'فاتورة بيع',
            TransactionType::PURCHASE_INVOICE => 'فاتورة شراء',
            TransactionType::CUSTOMER_PAYMENT => 'سند قبض',
            TransactionType::SUPPLIER_PAYMENT => 'سند دفع',
            TransactionType::REPAIR_COMMISSION => 'عمولة صيانة',
            TransactionType::TECHNICIAN_PAYMENT => 'سند صرف لخبير',
            TransactionType::OPENING => 'رصيد افتتاحي',
            TransactionType::REVERSAL => 'قيد عكسي',
            TransactionType::ADJUSTMENT => 'تسوية',
        };
    }
}
