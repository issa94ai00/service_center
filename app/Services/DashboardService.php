<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\PaymentType;
use App\Enums\RepairStatus;
use App\Models\Expense;
use App\Models\Material;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\Repair;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use App\Support\DateRange;
use App\Support\Features;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Assembles the dashboard payload in one pass.
 *
 * Everything is aggregated in SQL and every figure is derived here rather than
 * in Vue, so the dashboard cannot disagree with the reports or the ledger.
 */
class DashboardService
{
    /** Beyond this many days the trend switches from daily to monthly buckets. */
    protected const DAILY_TREND_LIMIT = 92;

    /** How many rows each shortlist carries. */
    protected const SHORTLIST_SIZE = 5;

    protected const RECENT_SIZE = 8;

    public function __construct(protected ReportService $reports) {}

    /**
     * The repair, technician and service sections are built only for a $user who may
     * see that module (its view permission, with its feature switched on), so the
     * payload never carries figures the screen would have to hide. Without a $user
     * every section is built.
     *
     * @return array<string, mixed>
     */
    public function build(?string $from = null, ?string $to = null, bool $compare = false, ?User $user = null): array
    {
        $today = CarbonImmutable::today();
        $start = $from ? CarbonImmutable::parse($from) : $today->startOfMonth();
        $end = $to ? CarbonImmutable::parse($to) : $today;

        $fromDate = $start->format('Y-m-d');
        $toDate = $end->format('Y-m-d');
        $days = (int) $start->diffInDays($end) + 1;

        $sections = $this->sections($user);
        $profit = $this->reports->profitSummary($fromDate, $toDate);

        return [
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
                'days' => $days,
            ],
            'sections' => $sections,
            'kpis' => $this->kpis($fromDate, $toDate, $today->format('Y-m-d')),
            'alerts' => $this->alerts($sections['repairs']),
            'trend' => $this->trend($start, $end),
            'top_customers' => $this->topCustomers($fromDate, $toDate),
            'top_materials' => $this->topMaterials($fromDate, $toDate),
            'top_repair_brands' => $sections['repairs'] ? $this->topRepairBrands($fromDate, $toDate) : null,
            'workshop' => $sections['repairs'] ? $this->workshopSummary($fromDate, $toDate) : null,
            'top_technicians' => $sections['technicians'] ? $this->topTechnicians($fromDate, $toDate) : null,
            'top_services' => $sections['services'] ? $this->topServices($profit) : null,
            'profit_overview' => $this->profitOverview($profit),
            'low_stock' => $this->reports->lowStock()->take(self::SHORTLIST_SIZE)->values(),
            'recent_documents' => $this->recentDocuments(),
            'comparison' => $compare ? $this->comparison($start, $days) : null,
        ];
    }

    /**
     * Which module sections $user may see on the dashboard.
     *
     * @return array{repairs: bool, technicians: bool, services: bool}
     */
    protected function sections(?User $user): array
    {
        // A switched-off module leaves the dashboard even for Master, who can still open it
        // from its own page. Technicians are paid from repairs, so they need both switches.
        return [
            'repairs' => Features::on('repairs') && ($user?->hasPermission('repairs.view') ?? true),
            'technicians' => Features::on('repairs') && Features::on('technicians')
                && ($user?->hasPermission('technicians.view') ?? true),
            'services' => Features::on('services') && ($user?->hasPermission('services.view') ?? true),
        ];
    }

    /**
     * The same span, shifted back once, so every period figure can carry a
     * change against it.
     *
     * Only the window figures are recomputed. The outstanding balances, the
     * shortlists, the trend and the alerts describe where the business stands
     * now rather than over a window, so there is nothing about them to
     * compare — and re-running them would double the cost of the dashboard for
     * five change badges.
     *
     * @return array<string, mixed>
     */
    protected function comparison(CarbonImmutable $start, int $days): array
    {
        $end = $start->subDay();
        $from = $end->subDays($days - 1);

        $fromDate = $from->format('Y-m-d');
        $toDate = $end->format('Y-m-d');

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'days' => $days,
            'kpis' => $this->periodKpis($fromDate, $toDate),
        ];
    }

    // -------------------------------------------------------------------- kpis

    /**
     * @return array<string, mixed>
     */
    protected function kpis(string $from, string $to, string $today): array
    {
        $salesToday = $this->documentTotals(
            SalesInvoice::query()->where('status', DocumentStatus::POSTED),
            'invoice_date',
            $today,
            $today,
        );

        $receivables = $this->reports->receivables()['totals']['balance'];
        $payables = $this->reports->payables()['totals']['balance'];

        return [
            'sales_today' => $salesToday,
            ...$this->periodKpis($from, $to),
            'receivables' => $receivables,
            'payables' => $payables,
            'net_position' => Money::fromDecimal($receivables)
                ->subtract(Money::fromDecimal($payables))
                ->toDecimal(),
        ];
    }

    /**
     * The figures that describe a window, and so are the only ones that mean
     * anything to compare between two of them. Shared by the period on screen
     * and the one behind it so the two cannot be measured differently.
     *
     * @return array<string, mixed>
     */
    protected function periodKpis(string $from, string $to): array
    {
        return [
            'sales_period' => $this->documentTotals(
                SalesInvoice::query()->where('status', DocumentStatus::POSTED),
                'invoice_date',
                $from,
                $to,
            ),
            'purchases_period' => $this->documentTotals(
                PurchaseInvoice::query()->where('status', DocumentStatus::POSTED),
                'purchase_date',
                $from,
                $to,
            ),
            'collected_period' => $this->paymentTotal(PaymentType::CUSTOMER, $from, $to),
            'paid_period' => $this->paymentTotal(PaymentType::SUPPLIER, $from, $to),
            // مصاريف التشغيل تخرج من الصندوق كما تخرج دفعات الموردين، فلوحة
            // لا تعدّها تُظهر يوماً رابحاً بينما دفع صاحبه إيجاره منه.
            'expenses_period' => $this->expenseTotals($from, $to),
        ];
    }

    /**
     * @return array{amount: string, count: int}
     */
    protected function expenseTotals(string $from, string $to): array
    {
        $row = Expense::query()
            ->counted()
            ->whereBetween('expense_date', DateRange::between($from, $to))
            ->selectRaw('COUNT(*) as entries, COALESCE(SUM(amount), 0) as amount')
            ->first();

        return [
            'amount' => Money::fromDecimal($row?->amount)->toDecimal(),
            'count' => (int) ($row?->entries ?? 0),
        ];
    }

    /**
     * @return array{amount: string, count: int}
     */
    protected function documentTotals(Builder $query, string $dateColumn, string $from, string $to): array
    {
        $row = $query
            ->whereBetween($dateColumn, DateRange::between($from, $to))
            ->selectRaw('COUNT(*) as documents, COALESCE(SUM(total), 0) as amount')
            ->first();

        return [
            'amount' => Money::fromDecimal($row?->amount)->toDecimal(),
            'count' => (int) ($row?->documents ?? 0),
        ];
    }

    protected function paymentTotal(PaymentType $type, string $from, string $to): string
    {
        $amount = Payment::query()
            ->where('status', DocumentStatus::POSTED)
            ->where('payment_type', $type)
            ->whereBetween('payment_date', DateRange::between($from, $to))
            ->sum('amount');

        return Money::fromDecimal($amount)->toDecimal();
    }

    // ------------------------------------------------------------------ alerts

    /**
     * Things that need someone's attention: unposted documents and stock that
     * has run down.
     *
     * @return array<string, int>
     */
    protected function alerts(bool $withRepairs = true): array
    {
        $lowStock = Material::query()
            ->where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock')
            ->selectRaw('COUNT(*) as low')
            ->selectRaw('SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) as depleted')
            ->first();

        $alerts = [
            'draft_sales' => SalesInvoice::query()->where('status', DocumentStatus::DRAFT)->count(),
            'draft_purchases' => PurchaseInvoice::query()->where('status', DocumentStatus::DRAFT)->count(),
            'draft_payments' => Payment::query()->where('status', DocumentStatus::DRAFT)->count(),
            'low_stock' => (int) ($lowStock?->low ?? 0),
            'out_of_stock' => (int) ($lowStock?->depleted ?? 0),
        ];

        if (! $withRepairs) {
            return $alerts;
        }

        return $alerts + [
            'repairs_ready' => Repair::query()->where('status', RepairStatus::READY)->count(),
            'repairs_waiting_approval' => Repair::query()->where('status', RepairStatus::WAITING_APPROVAL)->count(),
            'repairs_in_progress' => Repair::query()->whereIn('status', [
                RepairStatus::RECEIVED,
                RepairStatus::DIAGNOSING,
                RepairStatus::UNDER_REPAIR,
            ])->count(),
        ];
    }

    // ------------------------------------------------------------------- trend

    /**
     * Sales against purchases over the period.
     *
     * Buckets with no documents are filled with zeros rather than skipped — a
     * line chart that omits empty days draws a slope that never happened.
     *
     * @return array{granularity: string, points: array<int, array<string, mixed>>}
     */
    protected function trend(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $daily = $start->diffInDays($end) < self::DAILY_TREND_LIMIT;

        $sales = $this->bucketed(
            SalesInvoice::query()->where('status', DocumentStatus::POSTED),
            'invoice_date',
            $start,
            $end,
            $daily,
        );

        $purchases = $this->bucketed(
            PurchaseInvoice::query()->where('status', DocumentStatus::POSTED),
            'purchase_date',
            $start,
            $end,
            $daily,
        );

        $points = [];
        foreach ($this->buckets($start, $end, $daily) as $key => $label) {
            $points[] = [
                'key' => $key,
                'label' => $label,
                'sales' => Money::fromDecimal($sales[$key] ?? 0)->toDecimal(),
                'purchases' => Money::fromDecimal($purchases[$key] ?? 0)->toDecimal(),
            ];
        }

        return [
            'granularity' => $daily ? 'day' : 'month',
            'points' => $points,
        ];
    }

    /**
     * @return Collection<string, string>
     */
    protected function bucketed(Builder $query, string $dateColumn, CarbonImmutable $start, CarbonImmutable $end, bool $daily): Collection
    {
        $expression = $daily
            ? $dateColumn
            : $this->monthExpression($dateColumn);

        return $query
            ->whereBetween($dateColumn, DateRange::between($start->format('Y-m-d'), $end->format('Y-m-d')))
            ->selectRaw("{$expression} as bucket")
            ->selectRaw('COALESCE(SUM(total), 0) as amount')
            ->groupByRaw($expression)
            ->pluck('amount', 'bucket')
            ->mapWithKeys(fn ($amount, $bucket) => [
                substr((string) $bucket, 0, $daily ? 10 : 7) => (string) $amount,
            ]);
    }

    /**
     * Every bucket in the period, in order, keyed the same way as `bucketed()`.
     *
     * @return array<string, string>
     */
    protected function buckets(CarbonImmutable $start, CarbonImmutable $end, bool $daily): array
    {
        $buckets = [];
        $cursor = $daily ? $start : $start->startOfMonth();

        while ($cursor <= $end) {
            if ($daily) {
                $buckets[$cursor->format('Y-m-d')] = $cursor->format('m-d');
                $cursor = $cursor->addDay();
            } else {
                $buckets[$cursor->format('Y-m')] = $cursor->format('Y-m');
                $cursor = $cursor->addMonth();
            }
        }

        return $buckets;
    }

    /**
     * SQLite has no DATE_FORMAT and MySQL has no strftime, so the month bucket
     * expression has to follow the connection.
     */
    protected function monthExpression(string $column): string
    {
        return match (SalesInvoice::query()->getConnection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    // -------------------------------------------------------------- shortlists

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function topCustomers(string $from, string $to): Collection
    {
        return SalesInvoice::query()
            ->where('sales_invoices.status', DocumentStatus::POSTED)
            ->whereBetween('invoice_date', DateRange::between($from, $to))
            ->join('customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->groupBy('customers.id', 'customers.name')
            ->selectRaw('customers.id, customers.name')
            ->selectRaw('COUNT(*) as invoices')
            ->selectRaw('COALESCE(SUM(sales_invoices.total), 0) as total')
            ->orderByDesc('total')
            ->limit(self::SHORTLIST_SIZE)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'invoices' => (int) $row->invoices,
                'total' => Money::fromDecimal($row->total)->toDecimal(),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function topMaterials(string $from, string $to): Collection
    {
        return SalesInvoiceItem::query()
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('materials', 'materials.id', '=', 'sales_invoice_items.material_id')
            ->where('sales_invoices.status', DocumentStatus::POSTED)
            ->whereBetween('sales_invoices.invoice_date', DateRange::between($from, $to))
            ->whereNotNull('sales_invoice_items.material_id')
            ->groupBy('materials.id', 'materials.name')
            ->selectRaw('materials.id, materials.name')
            ->selectRaw('COALESCE(SUM(sales_invoice_items.quantity), 0) as quantity')
            ->selectRaw('COALESCE(SUM(sales_invoice_items.line_total), 0) as total')
            ->orderByDesc('total')
            ->limit(self::SHORTLIST_SIZE)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'quantity' => number_format((float) $row->quantity, 3, '.', ''),
                'total' => Money::fromDecimal($row->total)->toDecimal(),
            ]);
    }

    /**
     * The most recently posted documents across all three ledgers, merged.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function recentDocuments(): Collection
    {
        $sales = SalesInvoice::query()
            ->where('status', DocumentStatus::POSTED)
            ->with('customer:id,name')
            ->latest('invoice_date')->latest('id')
            ->limit(self::RECENT_SIZE)
            ->get()
            ->map(fn (SalesInvoice $invoice) => [
                'kind' => 'sales_invoice',
                'kind_label' => 'فاتورة بيع',
                'id' => $invoice->id,
                'reference_no' => $invoice->invoice_no,
                'date' => $invoice->invoice_date?->format('Y-m-d'),
                'party' => $invoice->customer?->name,
                'amount' => Money::fromDecimal($invoice->total)->toDecimal(),
                'direction' => 'in',
            ]);

        $purchases = PurchaseInvoice::query()
            ->where('status', DocumentStatus::POSTED)
            ->with('supplier:id,name')
            ->latest('purchase_date')->latest('id')
            ->limit(self::RECENT_SIZE)
            ->get()
            ->map(fn (PurchaseInvoice $invoice) => [
                'kind' => 'purchase_invoice',
                'kind_label' => 'فاتورة شراء',
                'id' => $invoice->id,
                'reference_no' => $invoice->invoice_no,
                'date' => $invoice->purchase_date?->format('Y-m-d'),
                'party' => $invoice->supplier?->name,
                'amount' => Money::fromDecimal($invoice->total)->toDecimal(),
                'direction' => 'out',
            ]);

        $payments = Payment::query()
            ->where('status', DocumentStatus::POSTED)
            ->with(['customer:id,name', 'supplier:id,name'])
            ->latest('payment_date')->latest('id')
            ->limit(self::RECENT_SIZE)
            ->get()
            ->map(fn (Payment $payment) => [
                'kind' => 'payment',
                'kind_label' => $payment->payment_type === PaymentType::CUSTOMER ? 'سند قبض' : 'سند دفع',
                'id' => $payment->id,
                'reference_no' => $payment->reference_no,
                'date' => $payment->payment_date?->format('Y-m-d'),
                'party' => $payment->customer?->name ?? $payment->supplier?->name,
                'amount' => Money::fromDecimal($payment->amount)->toDecimal(),
                'direction' => $payment->payment_type === PaymentType::CUSTOMER ? 'in' : 'out',
            ]);

        $expenses = Expense::query()
            ->counted()
            ->with('category:id,name')
            ->latest('expense_date')->latest('id')
            ->limit(self::RECENT_SIZE)
            ->get()
            ->map(fn (Expense $expense) => [
                'kind' => 'expense',
                'kind_label' => 'سند مصروف',
                'id' => $expense->id,
                'reference_no' => $expense->expense_no,
                'date' => $expense->expense_date?->format('Y-m-d'),
                // المستفيد إن ذُكر، وإلّا فالباب: سطر بلا طرف لا يُقرأ.
                'party' => $expense->payee ?: $expense->category?->name,
                'amount' => Money::fromDecimal($expense->amount)->toDecimal(),
                'direction' => 'out',
            ]);

        return $sales->concat($purchases)->concat($payments)->concat($expenses)
            ->sortByDesc('date')
            ->take(self::RECENT_SIZE)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function topRepairBrands(string $from, string $to): Collection
    {
        return Repair::query()
            ->whereBetween('received_at', DateRange::between($from, $to))
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->groupBy('brand')
            ->selectRaw('brand, COUNT(*) as count')
            ->orderByDesc('count')
            ->limit(self::SHORTLIST_SIZE)
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->brand,
                'count' => (int) $row->count,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function workshopSummary(string $from, string $to): array
    {
        $received = Repair::query()
            ->whereBetween('received_at', DateRange::between($from, $to))
            ->count();

        $delivered = Repair::query()
            ->where('status', RepairStatus::DELIVERED)
            ->whereBetween('delivered_at', DateRange::between($from, $to))
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(final_cost), 0) as total_amount')
            ->first();

        $activeCount = Repair::query()
            ->whereNotIn('status', [RepairStatus::DELIVERED, RepairStatus::CANCELLED])
            ->count();

        $readyCount = Repair::query()
            ->where('status', RepairStatus::READY)
            ->count();

        $waitingApprovalCount = Repair::query()
            ->where('status', RepairStatus::WAITING_APPROVAL)
            ->count();

        return [
            'received_count' => $received,
            'delivered_count' => (int) ($delivered?->total_count ?? 0),
            'delivered_revenue' => Money::fromDecimal($delivered?->total_amount ?? 0)->toDecimal(),
            'active_in_shop' => $activeCount,
            'ready_for_pickup' => $readyCount,
            'waiting_approval' => $waitingApprovalCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary  ReportService::profitSummary() for the period
     * @return array<string, mixed>
     */
    protected function profitOverview(array $summary): array
    {
        return [
            'revenue' => $summary['revenue']['total'],
            'cogs' => $summary['cogs']['total'],
            'gross_profit' => $summary['gross_profit']['total'],
            'gross_margin' => $summary['gross_profit']['margin_percent'],
            'operating_expenses' => $summary['operating_expenses']['total'],
            'technician_commissions' => $summary['technician_commissions']['total'],
            'operating_costs' => $summary['operating_costs']['total'],
            'net_profit' => $summary['net_profit']['total'],
            'net_margin' => $summary['net_profit']['margin_percent'],
            'is_loss' => $summary['net_profit']['is_loss'],
            'net_cash_flow' => $summary['cash_flow']['net_cash_flow'],
        ];
    }

    /**
     * Technicians ranked by commission earned in the period, with what the shop still
     * owes all active technicians (positive = owed to them), as the technicians report.
     *
     * @return array<string, mixed>
     */
    protected function topTechnicians(string $from, string $to): array
    {
        $summary = $this->reports->technicianSummary([
            'from' => $from,
            'to' => $to,
            'only_active' => true,
            'per_page' => self::SHORTLIST_SIZE,
        ]);

        return [
            'rows' => $summary['rows']
                ->filter(fn (array $row) => $row['repairs_count'] > 0 || (float) $row['commission'] != 0.0)
                ->map(fn (array $row) => collect($row)->only(['id', 'name', 'repairs_count', 'revenue', 'commission'])->all())
                ->values(),
            'balance_owed' => $summary['totals']['balance'],
        ];
    }

    /**
     * The best-selling services of the period, from the profit summary already built.
     *
     * @param  array<string, mixed>  $profit  ReportService::profitSummary() for the period
     * @return array<int, array<string, mixed>>
     */
    protected function topServices(array $profit): array
    {
        return array_slice($profit['top_services'], 0, self::SHORTLIST_SIZE);
    }
}
