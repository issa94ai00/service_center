<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\PaymentType;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\DocumentLockedException;
use App\Models\CustomerTransaction;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SupplierTransaction;
use App\Models\TechnicianTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private readonly NumberingService $numbers) {}

    /**
     * Persist a draft payment with its allocations (draft only).
     */
    public function saveDraft(Payment $payment, array $data, int $userId): Payment
    {
        $payment->fill($this->draftPayload($data, $userId));

        // Only when the caller said something about it: a plain edit from the
        // payments screen must not unlink a receipt from its repair.
        if (array_key_exists('repair_id', $data)) {
            $payment->repair_id = $data['repair_id'];
        }

        $payment->save();

        $payment->allocations()->delete();
        foreach (array_values($data['allocations'] ?? []) as $index => $raw) {
            PaymentAllocation::query()->create([
                'payment_id' => $payment->id,
                'invoice_type' => $raw['invoice_type'],
                'sales_invoice_id' => $raw['invoice_type'] === 'SALES' ? $raw['sales_invoice_id'] : null,
                'purchase_invoice_id' => $raw['invoice_type'] === 'PURCHASE' ? $raw['purchase_invoice_id'] : null,
                'amount' => $raw['amount'],
            ]);
        }

        return $payment->fresh();
    }

    public function post(Payment $payment, int $userId): Payment
    {
        return DB::transaction(function () use ($payment, $userId) {
            /** @var Payment $payment */
            $payment->lockForUpdate();
            $this->assertStatus($payment, DocumentStatus::DRAFT);

            // An unallocated payment is money on account: a repair deposit
            // taken before any invoice exists, credited to the customer and
            // matched against the invoice later. Everything else must still
            // name the invoices it settles.
            $payment->load('allocations');

            // سند صرف لخبير صيانة يُنقص رصيده مباشرة؛ لا توجد فاتورة شراء
            // يُخصَّص عليها، وتخصيصه على واحدة يعني قيداً في الدفتر الخطأ.
            if ($payment->payment_type === PaymentType::TECHNICIAN && $payment->allocations->isNotEmpty()) {
                throw new BusinessRuleException(__('سندات خبراء الصيانة لا تُخصَّص على فواتير.'));
            }

            $allocated = Money::zero();
            foreach ($payment->allocations as $allocation) {
                $invoice = $this->resolveInvoice($allocation->invoice_type, $allocation->invoiceId());
                $invoice->lockForUpdate();
                $this->assertInvoicePostable($invoice, $payment, $allocation);

                $newPaid = Money::fromDecimal($invoice->paid_amount)->add(Money::fromDecimal($allocation->amount));
                $newRemaining = Money::fromDecimal($invoice->total)->subtract($newPaid);
                $invoice->update([
                    'paid_amount' => $newPaid->toDecimal(),
                    'remaining' => $newRemaining->toDecimal(),
                ]);

                $allocated = $allocated->add(Money::fromDecimal($allocation->amount));
            }

            if ($payment->allocations->isNotEmpty() && $allocated->toCents() !== Money::fromDecimal($payment->amount)->toCents()) {
                throw new BusinessRuleException(__('Payment amount (:total) must equal the sum of allocations (:allocated).', [
                    'total' => $payment->amount,
                    'allocated' => $allocated->toDecimal(),
                ]));
            }

            // A receipt raised against a repair numbers itself on the
            // maintenance series, so a deposit taken at the bench is never
            // confused with a collection against a counter sale.
            $payment->reference_no = $payment->reference_no
                ?: $this->numbers->next($payment->repair_id ? 'REPAIR_PAYMENT' : 'PAYMENT');
            $payment->status = DocumentStatus::POSTED;
            $payment->posted_by = $userId;
            $payment->posted_at = now();
            $payment->save();

            // The whole receipt hits the account, whether or not it was
            // matched to an invoice; an on-account payment is a credit the
            // customer can spend on a later invoice.
            $this->writeLedger($payment, (string) $payment->amount, $userId, false);

            return $payment->fresh();
        });
    }

    /**
     * Match money already sitting on the customer's account against an invoice.
     *
     * A repair deposit is posted before any invoice exists, so it reaches the
     * ledger as a plain credit. When the repair is finally invoiced that credit
     * has to settle it — but the ledger entry is already there, so this writes
     * the allocation and the invoice's paid figures only. Writing a second
     * ledger line here would credit the customer twice for one payment.
     *
     * @return string the amount actually applied
     */
    public function applyCredit(Payment $payment, SalesInvoice $invoice, string $amount): string
    {
        return DB::transaction(function () use ($payment, $invoice, $amount) {
            /** @var Payment $payment */
            $payment->lockForUpdate();
            /** @var SalesInvoice $invoice */
            $invoice->lockForUpdate();

            $this->assertStatus($payment, DocumentStatus::POSTED);

            if ($invoice->status !== DocumentStatus::POSTED) {
                throw new BusinessRuleException(__('Only posted invoices can be settled.'));
            }

            if ($payment->payment_type !== PaymentType::CUSTOMER || $payment->customer_id !== $invoice->customer_id) {
                throw new BusinessRuleException(__('The invoice belongs to a different customer.'));
            }

            $requested = Money::fromDecimal($amount);
            if ($requested->isNegative() || $requested->isZero()) {
                throw new BusinessRuleException(__('Allocation amount must be greater than zero.'));
            }

            $available = $this->unallocated($payment);
            if ($available->toCents() < $requested->toCents()) {
                throw new BusinessRuleException(__('Payment :no only has :available left on account.', [
                    'no' => $payment->reference_no,
                    'available' => $available->toDecimal(),
                ]));
            }

            $remaining = Money::fromDecimal($invoice->remaining);
            if ($remaining->toCents() < $requested->toCents()) {
                throw new BusinessRuleException(__('Allocation of :amount exceeds the remaining balance (:remaining) of :no.', [
                    'amount' => $requested->toDecimal(),
                    'remaining' => $invoice->remaining,
                    'no' => $invoice->invoice_no,
                ]));
            }

            PaymentAllocation::query()->create([
                'payment_id' => $payment->id,
                'invoice_type' => 'SALES',
                'sales_invoice_id' => $invoice->id,
                'amount' => $requested->toDecimal(),
            ]);

            $newPaid = Money::fromDecimal($invoice->paid_amount)->add($requested);
            $invoice->update([
                'paid_amount' => $newPaid->toDecimal(),
                'remaining' => Money::fromDecimal($invoice->total)->subtract($newPaid)->toDecimal(),
            ]);

            return $requested->toDecimal();
        });
    }

    /**
     * المبلغ الذي يدفعه العميل لحظة ترحيل فاتورته.
     *
     * البيع عند الصندوق ليس ديناً في الغالب: الزبون يدفع والفاتورة تُطبع في
     * النفس نفسه. من دون هذا كان على البائع أن يغادر شاشة البيع إلى شاشة
     * السندات ليقول ما قاله للتوّ — أو يؤجّل فيبقى في الدفتر دَينٌ سُدِّد فعلاً.
     *
     * السند يُخصَّص على الفاتورة لا يُترك على الحساب: المال له وجهة معروفة
     * هنا، وتركه عائماً يجعل الفاتورة تبدو غير مسدَّدة وحساب العميل دائناً.
     *
     * @param  array{payment_method?: string, notes?: ?string}  $data
     */
    public function collectOnSalesInvoice(SalesInvoice $invoice, string $amount, array $data, int $userId): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $data, $userId) {
            /** @var SalesInvoice $invoice */
            $invoice->lockForUpdate();

            if ($invoice->status !== DocumentStatus::POSTED) {
                throw new BusinessRuleException(__('Only posted invoices can be settled.'), 'collect_amount');
            }

            $collect = Money::fromDecimal($amount);
            if ($collect->isNegative() || $collect->isZero()) {
                throw new BusinessRuleException(__('المبلغ المدفوع يجب أن يكون أكبر من صفر.'), 'collect_amount');
            }

            // الحدّ هو المتبقي لا الإجمالي: فاتورة صيانة رُحِّلت بعد عربون
            // سبقها تحمل جزءاً مسدَّداً قبل أن يصل هذا السند.
            $remaining = Money::fromDecimal($invoice->remaining);
            if ($collect->toCents() > $remaining->toCents()) {
                throw new BusinessRuleException(__('المبلغ المدفوع (:collect) يتجاوز المتبقي على الفاتورة (:remaining).', [
                    'collect' => $collect->toDecimal(),
                    'remaining' => $remaining->toDecimal(),
                ]), 'collect_amount');
            }

            $payment = $this->saveDraft(new Payment, [
                'payment_type' => PaymentType::CUSTOMER->value,
                'customer_id' => $invoice->customer_id,
                'amount' => $collect->toDecimal(),
                'payment_method' => $data['payment_method'] ?? 'CASH',
                // بتاريخ الفاتورة لا بتاريخ اليوم: فاتورة بتاريخ أمس يجب ألّا
                // يسبقها قبضها في كشف الحساب.
                'payment_date' => $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
                'notes' => $data['notes'] ?? __('تسديد عند ترحيل الفاتورة :no', ['no' => $invoice->invoice_no]),
                'allocations' => [[
                    'invoice_type' => 'SALES',
                    'sales_invoice_id' => $invoice->id,
                    'amount' => $collect->toDecimal(),
                ]],
            ], $userId);

            return $this->post($payment, $userId);
        });
    }

    /**
     * The part of a posted payment that no invoice has claimed yet.
     */
    public function unallocated(Payment $payment): Money
    {
        $allocated = $payment->allocations()->sum('amount');

        return Money::fromDecimal($payment->amount)->subtract(Money::fromDecimal($allocated));
    }

    public function cancel(Payment $payment, int $userId, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $userId, $reason) {
            /** @var Payment $payment */
            $payment->lockForUpdate();
            $this->assertStatus($payment, DocumentStatus::POSTED);

            $payment->load('allocations');

            foreach ($payment->allocations as $allocation) {
                $invoice = $this->resolveInvoice($allocation->invoice_type, $allocation->invoiceId());
                $invoice->lockForUpdate();
                if ($invoice->status !== DocumentStatus::POSTED) {
                    throw new BusinessRuleException(__('An allocated invoice is not active; cannot reverse this payment.'));
                }

                $newPaid = Money::fromDecimal($invoice->paid_amount)->subtract(Money::fromDecimal($allocation->amount));
                if ($newPaid->isNegative()) {
                    throw new BusinessRuleException(__('Invoice paid amount would become negative; cannot cancel this payment.'));
                }
                $newRemaining = Money::fromDecimal($invoice->total)->subtract($newPaid);
                $invoice->update([
                    'paid_amount' => $newPaid->toDecimal(),
                    'remaining' => $newRemaining->toDecimal(),
                ]);
            }

            $this->writeLedger($payment, (string) $payment->amount, $userId, true);

            $payment->status = DocumentStatus::CANCELLED;
            $payment->cancelled_by = $userId;
            $payment->cancelled_at = now();
            $payment->cancellation_reason = $reason;
            $payment->save();

            return $payment->fresh();
        });
    }

    public function deleteDraft(Payment $payment): void
    {
        $this->assertStatus($payment, DocumentStatus::DRAFT);
        $payment->allocations()->delete();
        $payment->delete();
    }

    // ---------------------------------------------------------------- internals

    protected function draftPayload(array $data, int $userId): array
    {
        return [
            'payment_type' => $data['payment_type'],
            'customer_id' => ($data['payment_type'] ?? null) === PaymentType::CUSTOMER->value ? $data['customer_id'] : null,
            'supplier_id' => ($data['payment_type'] ?? null) === PaymentType::SUPPLIER->value ? $data['supplier_id'] : null,
            'technician_id' => ($data['payment_type'] ?? null) === PaymentType::TECHNICIAN->value ? $data['technician_id'] : null,
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'] ?? 'CASH',
            'reference_no' => $data['reference_no'] ?? null,
            'payment_date' => $data['payment_date'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
            'status' => DocumentStatus::DRAFT,
            'recorded_by' => $userId,
        ];
    }

    protected function resolveInvoice(string $invoiceType, int $id): SalesInvoice|PurchaseInvoice
    {
        return $invoiceType === 'SALES'
            ? SalesInvoice::query()->whereKey($id)->firstOrFail()
            : PurchaseInvoice::query()->whereKey($id)->firstOrFail();
    }

    protected function assertInvoicePostable(SalesInvoice|PurchaseInvoice $invoice, Payment $payment, PaymentAllocation $allocation): void
    {
        if ($invoice->status !== DocumentStatus::POSTED) {
            throw new BusinessRuleException(__('Only posted invoices can be settled.'));
        }

        if ($payment->payment_type === PaymentType::CUSTOMER && ! $invoice instanceof SalesInvoice) {
            throw new BusinessRuleException(__('Customer payments must allocate sales invoices.'));
        }
        if ($payment->payment_type === PaymentType::SUPPLIER && ! $invoice instanceof PurchaseInvoice) {
            throw new BusinessRuleException(__('Supplier payments must allocate purchase invoices.'));
        }

        if ($payment->payment_type === PaymentType::CUSTOMER && $invoice instanceof SalesInvoice && $invoice->customer_id !== $payment->customer_id) {
            throw new BusinessRuleException(__('The invoice belongs to a different customer.'));
        }
        if ($payment->payment_type === PaymentType::SUPPLIER && $invoice instanceof PurchaseInvoice && $invoice->supplier_id !== $payment->supplier_id) {
            throw new BusinessRuleException(__('The invoice belongs to a different supplier.'));
        }

        $remaining = Money::fromDecimal($invoice->remaining);
        if ($remaining->toCents() < Money::fromDecimal($allocation->amount)->toCents()) {
            throw new BusinessRuleException(__('Allocation of :amount exceeds the remaining balance (:remaining) of :no.', [
                'amount' => $allocation->amount,
                'remaining' => $invoice->remaining,
                'no' => $invoice->invoice_no,
            ]));
        }
    }

    protected function writeLedger(Payment $payment, string $amount, int $userId, bool $reversal): void
    {
        $description = $reversal
            ? __('Cancelled payment :no', ['no' => $payment->reference_no])
            : __('Payment :no', ['no' => $payment->reference_no]);

        if ($payment->payment_type === PaymentType::TECHNICIAN) {
            // الصرف للخبير مدين: يُنقص ما يستحقّه، وعكسه عند الإلغاء دائن.
            TechnicianTransaction::query()->create([
                'technician_id' => $payment->technician_id,
                'transaction_date' => $payment->payment_date,
                'type' => $reversal ? TransactionType::REVERSAL : TransactionType::TECHNICIAN_PAYMENT,
                'reference_type' => Payment::class,
                'reference_id' => $payment->id,
                'debit' => $reversal ? '0.00' : $amount,
                'credit' => $reversal ? $amount : '0.00',
                'description' => $description,
                'created_by' => $userId,
            ]);

            return;
        }

        if ($payment->payment_type === PaymentType::CUSTOMER) {
            CustomerTransaction::query()->create([
                'customer_id' => $payment->customer_id,
                'transaction_date' => $payment->payment_date,
                'type' => $reversal ? TransactionType::REVERSAL : TransactionType::CUSTOMER_PAYMENT,
                'reference_type' => Payment::class,
                'reference_id' => $payment->id,
                'debit' => $reversal ? $amount : '0.00',
                'credit' => $reversal ? '0.00' : $amount,
                'description' => $description,
                'created_by' => $userId,
            ]);
        } else {
            SupplierTransaction::query()->create([
                'supplier_id' => $payment->supplier_id,
                'transaction_date' => $payment->payment_date,
                'type' => $reversal ? TransactionType::REVERSAL : TransactionType::SUPPLIER_PAYMENT,
                'reference_type' => Payment::class,
                'reference_id' => $payment->id,
                'debit' => $reversal ? '0.00' : $amount,
                'credit' => $reversal ? $amount : '0.00',
                'description' => $description,
                'created_by' => $userId,
            ]);
        }
    }

    protected function assertStatus(Payment $payment, DocumentStatus $expected): void
    {
        if ($payment->status !== $expected) {
            throw new DocumentLockedException();
        }
    }
}