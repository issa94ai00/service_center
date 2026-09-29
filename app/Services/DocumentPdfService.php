<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\Repair;
use App\Models\SalesInvoice;
use App\Models\Setting;
use App\Models\Supplier;
use App\Services\Pdf\PdfRenderer;
use App\Support\ArabicNumberToWords;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Builds the printable documents: invoices, payment receipts and statements.
 *
 * Only presentation happens here — every figure is read straight off the posted
 * record or out of StatementService, never recomputed, so a printed document
 * can never disagree with the ledger it came from.
 */
class DocumentPdfService
{
    /** A statement PDF renders in full; this bounds a pathological range. */
    public const STATEMENT_ROW_LIMIT = 3000;

    public function __construct(
        protected PdfRenderer $renderer,
        protected StatementService $statements,
        protected RepairSettlementService $settlements,
    ) {}

    // ---------------------------------------------------------------- invoices

    public function salesInvoice(SalesInvoice $invoice): string
    {
        $invoice->load(['customer', 'repair', 'items.service', 'items.material', 'items.unit']);

        // An invoice raised at a hand-over prints as what it is. The customer
        // keeps it next to the intake receipt, so it names the device and
        // quotes that receipt's number rather than looking like a shop sale.
        $repair = $invoice->repair;

        $lines = $invoice->items->map(fn ($item) => [
            'description' => $item->description
                ?: ($item->service?->name ?? $item->material?->name ?? '—'),
            'kind' => $item->line_type->value === 'SERVICE' ? 'خدمة' : 'مادة',
            'unit' => $item->unit?->name_ar,
            'quantity' => $this->quantity($item->quantity),
            'unit_price' => $this->money($item->unit_price),
            'line_total' => $this->money($item->line_total),
        ]);

        return $this->renderer->render('pdf.invoice', [
            'company' => $this->company(),
            'invoice' => $invoice,
            'party' => $invoice->customer,
            'lines' => $lines,
            'showKind' => true,
            'documentDate' => $invoice->invoice_date?->format('Y-m-d'),
            'dueDate' => $invoice->due_date?->format('Y-m-d'),
            'totals' => $this->invoiceTotals($invoice),
            'reference' => $repair ? [
                'label' => 'إيصال الاستلام',
                'value' => $repair->receipt_no,
                'note_label' => 'الجهاز',
                'note' => collect([$repair->brand, $repair->model])->filter()->join(' ') ?: $repair->device_type,
            ] : null,
            'labels' => [
                'title' => $repair ? 'فاتورة صيانة' : 'فاتورة بيع',
                'party' => 'العميل',
                'status' => $this->statusLabel($invoice->status),
                'signature_party' => 'توقيع العميل',
            ],
        ], ($repair ? 'فاتورة صيانة ' : 'فاتورة بيع ').($invoice->invoice_no ?? ''));
    }

    public function purchaseInvoice(PurchaseInvoice $invoice): string
    {
        $invoice->load(['supplier', 'items.material', 'items.unit']);

        $lines = $invoice->items->map(fn ($item) => [
            'description' => $item->description ?: ($item->material?->name ?? '—'),
            'kind' => 'مادة',
            'unit' => $item->unit?->name_ar,
            'quantity' => $this->quantity($item->quantity),
            'unit_price' => $this->money($item->unit_price),
            'line_total' => $this->money($item->line_total),
        ]);

        return $this->renderer->render('pdf.invoice', [
            'company' => $this->company(),
            'invoice' => $invoice,
            'party' => $invoice->supplier,
            'lines' => $lines,
            // Purchase lines are always materials, so the type column is noise.
            'showKind' => false,
            'documentDate' => $invoice->purchase_date?->format('Y-m-d'),
            'dueDate' => $invoice->due_date?->format('Y-m-d'),
            'totals' => $this->invoiceTotals($invoice),
            'reference' => null,
            'labels' => [
                'title' => 'فاتورة شراء',
                'party' => 'المورد',
                'status' => $this->statusLabel($invoice->status),
                'signature_party' => 'توقيع المورد',
            ],
        ], 'فاتورة شراء '.($invoice->invoice_no ?? ''));
    }

    // ----------------------------------------------------------------- receipt

    public function paymentReceipt(Payment $payment): string
    {
        $payment->load([
            'customer', 'supplier', 'repair',
            'allocations.salesInvoice:id,invoice_no,invoice_date',
            'allocations.purchaseInvoice:id,invoice_no,purchase_date',
        ]);

        $isCustomer = $payment->payment_type === PaymentType::CUSTOMER;
        // A receipt taken at the maintenance counter is handed over with the
        // device still in the shop; it has to say which device, or the customer
        // holds a piece of paper that proves nothing in particular.
        $repair = $payment->repair;
        $company = $this->company();

        $allocations = $payment->allocations->map(function ($allocation) {
            $invoice = $allocation->invoice_type === 'SALES'
                ? $allocation->salesInvoice
                : $allocation->purchaseInvoice;

            return [
                'invoice_no' => $invoice?->invoice_no ?? '—',
                'invoice_date' => $invoice?->invoice_date?->format('Y-m-d')
                    ?? $invoice?->purchase_date?->format('Y-m-d') ?? '—',
                'amount' => $this->money($allocation->amount),
            ];
        });

        return $this->renderer->render('pdf.payment-receipt', [
            'company' => $company,
            'payment' => $payment,
            'party' => $isCustomer ? $payment->customer : $payment->supplier,
            'paymentDate' => $payment->payment_date?->format('Y-m-d'),
            'amount' => $this->money($payment->amount),
            'amountInWords' => ArabicNumberToWords::money($payment->amount, $company['currency_name']),
            'allocations' => $allocations,
            'reference' => $repair ? [
                'label' => 'إيصال الاستلام',
                'value' => $repair->receipt_no,
                'note_label' => 'الجهاز',
                'note' => collect([$repair->brand, $repair->model])->filter()->join(' ') ?: $repair->device_type,
            ] : null,
            'labels' => [
                'title' => $isCustomer
                    ? ($repair ? 'سند قبض صيانة' : 'سند قبض')
                    : 'سند دفع',
                'party' => $isCustomer ? 'المستلم منه' : 'المدفوع له',
                'amount_label' => $isCustomer ? 'المبلغ المقبوض' : 'المبلغ المدفوع',
                'method' => $this->methodLabel($payment->payment_method),
                'status' => $this->statusLabel($payment->status),
                'signature_party' => $isCustomer ? 'توقيع المستلم منه' : 'توقيع المستلم',
            ],
        ], ($isCustomer ? ($repair ? 'سند قبض صيانة ' : 'سند قبض ') : 'سند دفع ').($payment->reference_no ?? ''));
    }

    // ------------------------------------------------- maintenance receipt

    /**
     * Receipt handed to a customer when a device is handed in for repair.
     */
    public function repairReceipt(Repair $repair): string
    {
        $repair->load(['customer', 'salesInvoice']);

        $cost = $repair->chargeableCost() !== null
            ? $this->money($repair->chargeableCost())
            : null;

        // The customer walks out with this piece of paper; if they left a
        // deposit it has to be on it, next to what is still owed.
        $settlement = $this->settlements->summary($repair);
        $paid = Money::fromDecimal($settlement['paid']);
        $due = Money::fromDecimal($settlement['due']);

        return $this->renderer->render('pdf.repair-receipt', [
            'company' => $this->company(),
            'repair' => $repair,
            'party' => $repair->customer,
            'receiptDate' => $repair->received_at?->format('Y-m-d H:i'),
            'deliveredDate' => $repair->delivered_at?->format('Y-m-d H:i'),
            'cost' => $cost,
            'costLabel' => $repair->final_cost !== null ? 'التكلفة النهائية' : 'التكلفة المقدرة',
            'paid' => $paid->isZero() ? null : $this->money($settlement['paid']),
            'due' => $paid->isZero() ? null : $this->money($due->absolute()->toDecimal()),
            'dueLabel' => $due->isNegative() ? 'رصيد للعميل' : 'المتبقي',
            'invoiceNo' => $settlement['invoice']['invoice_no'] ?? null,
            'labels' => [
                'title' => 'إيصال استلام جهاز صيانة',
                'party' => 'العميل',
                'status' => $repair->status->label(),
            ],
        ], 'استلام صيانة '.Setting::get('repair_receipt_prefix', 'RPR-').$repair->id);
    }

    // -------------------------------------------------------------- statements

    /**
     * @param  array{from?: ?string, to?: ?string}  $filters
     */
    public function customerStatement(Customer $customer, array $filters = []): string
    {
        return $this->statement(
            $this->statements->forCustomer($customer, $this->statementFilters($filters))['statement'],
            title: 'كشف حساب عميل',
            party: 'العميل',
            owed: 'الرصيد مستحق على العميل',
            prepaid: 'الرصيد لصالح العميل',
        );
    }

    /**
     * @param  array{from?: ?string, to?: ?string}  $filters
     */
    public function supplierStatement(Supplier $supplier, array $filters = []): string
    {
        return $this->statement(
            $this->statements->forSupplier($supplier, $this->statementFilters($filters))['statement'],
            title: 'كشف حساب مورد',
            party: 'المورد',
            owed: 'الرصيد مستحق للمورد',
            prepaid: 'الرصيد لصالحنا',
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $statement
     */
    protected function statement(array $statement, string $title, string $party, string $owed, string $prepaid): string
    {
        $balance = (float) $statement['closing_balance'];

        return $this->renderer->render('pdf.statement', [
            'company' => $this->company(),
            'statement' => $statement,
            'printedOn' => now()->format('Y-m-d'),
            'money' => fn ($value) => $this->money($value),
            'rowLimit' => self::STATEMENT_ROW_LIMIT,
            'truncated' => count($statement['rows']) >= self::STATEMENT_ROW_LIMIT,
            'balanceHint' => match (true) {
                $balance > 0 => $owed,
                $balance < 0 => $prepaid,
                default => 'الحساب مسدد بالكامل',
            },
            'labels' => ['title' => $title, 'party' => $party],
        ], $title.' — '.$statement['party']['name']);
    }

    /**
     * @param  array{from?: ?string, to?: ?string}  $filters
     * @return array{from: ?string, to: ?string, per_page: int}
     */
    protected function statementFilters(array $filters): array
    {
        return [
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            // A PDF is not paginated, so the whole period is rendered at once.
            'per_page' => self::STATEMENT_ROW_LIMIT,
            'max_per_page' => self::STATEMENT_ROW_LIMIT,
        ];
    }

    /**
     * @param  SalesInvoice|PurchaseInvoice  $invoice
     * @return array<string, mixed>
     */
    protected function invoiceTotals($invoice): array
    {
        return [
            'subtotal' => $this->money($invoice->subtotal),
            'discount' => $this->money($invoice->discount_amount),
            'has_discount' => (float) $invoice->discount_amount > 0,
            'total' => $this->money($invoice->total),
            'paid' => $this->money($invoice->paid_amount),
            'remaining' => $this->money($invoice->remaining),
        ];
    }

    /**
     * @return array<string, ?string>
     */
    protected function company(): array
    {
        $settings = Setting::query()->get()->keyBy('key');

        $value = fn (string $key, ?string $default = null) => filled($settings->get($key)?->value)
            ? $settings->get($key)->value
            : $default;

        return [
            'name' => $value('company_name', config('app.name')),
            'address' => $value('company_address'),
            'phone' => $value('company_phone'),
            'email' => $value('company_email'),
            'tax_number' => $value('company_tax_number'),
            'currency' => $value('currency_symbol', 'ل.س'),
            'currency_name' => $value('currency_name', 'ليرة سورية'),
        ];
    }

    protected function money(int|float|string|null $value): string
    {
        return number_format((float) Money::fromDecimal($value)->toDecimal(), 2, '.', ',');
    }

    protected function quantity(int|float|string|null $value): string
    {
        // Trim trailing zeros so "20.000" prints as "20" but "1.500" survives.
        return rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.') ?: '0';
    }

    protected function statusLabel(DocumentStatus $status): string
    {
        return match ($status) {
            DocumentStatus::DRAFT => 'مسودة',
            DocumentStatus::POSTED => 'معتمدة',
            DocumentStatus::CANCELLED => 'ملغاة',
        };
    }

    protected function methodLabel(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::CASH => 'نقدًا',
            PaymentMethod::BANK => 'حوالة مصرفية',
            PaymentMethod::OTHER => 'أخرى',
        };
    }
}
