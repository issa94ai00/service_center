<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\LineType;
use App\Enums\PaymentType;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\DocumentLockedException;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Material;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payments settle posted invoices: they move paid/remaining on the document and
 * write the opposite side of the party ledger.
 */
class PaymentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $payments;

    protected InvoiceService $invoices;

    protected User $user;

    protected Customer $customer;

    protected Supplier $supplier;

    protected Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['invoice_prefix', 'SL-'], ['purchase_prefix', 'PR-'], ['payment_prefix', 'PAY-']] as [$key, $value]) {
            Setting::query()->create(['key' => $key, 'value' => $value, 'group' => 'numbers', 'type' => 'string']);
        }

        $this->payments = app(PaymentService::class);
        $this->invoices = app(InvoiceService::class);
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->material = Material::factory()->inStock('1000.000')->create();
    }

    // ------------------------------------------------------- customer payments

    public function test_a_payment_settles_part_of_an_invoice(): void
    {
        $invoice = $this->salesInvoice('1000.00');

        $payment = $this->postPayment($this->customerPayment('400.00', $invoice));

        $this->assertSame(DocumentStatus::POSTED, $payment->status);
        $this->assertSame('PAY-000001', $payment->reference_no);

        $invoice->refresh();
        $this->assertSame('400.00', $invoice->paid_amount);
        $this->assertSame('600.00', $invoice->remaining);
    }

    public function test_a_customer_payment_credits_the_customer_ledger(): void
    {
        $invoice = $this->salesInvoice('1000.00');
        $this->postPayment($this->customerPayment('400.00', $invoice));

        $payment = CustomerTransaction::query()
            ->where('type', TransactionType::CUSTOMER_PAYMENT)->sole();

        $this->assertSame('400.00', $payment->credit);
        $this->assertSame('0.00', $payment->debit);

        // 1000 invoiced - 400 paid = 600 outstanding.
        $net = CustomerTransaction::query()->sum('debit') - CustomerTransaction::query()->sum('credit');
        $this->assertEquals(600, $net);
    }

    public function test_settling_an_invoice_in_full_leaves_nothing_remaining(): void
    {
        $invoice = $this->salesInvoice('1000.00');
        $this->postPayment($this->customerPayment('1000.00', $invoice));

        $invoice->refresh();
        $this->assertSame('1000.00', $invoice->paid_amount);
        $this->assertSame('0.00', $invoice->remaining);
    }

    public function test_two_payments_accumulate_against_one_invoice(): void
    {
        $invoice = $this->salesInvoice('1000.00');

        $this->postPayment($this->customerPayment('400.00', $invoice));
        $this->postPayment($this->customerPayment('250.00', $invoice->fresh()));

        $invoice->refresh();
        $this->assertSame('650.00', $invoice->paid_amount);
        $this->assertSame('350.00', $invoice->remaining);
    }

    public function test_one_payment_can_span_several_invoices(): void
    {
        $first = $this->salesInvoice('300.00');
        $second = $this->salesInvoice('700.00');

        $payment = $this->payments->saveDraft(new Payment, [
            'payment_type' => PaymentType::CUSTOMER->value,
            'customer_id' => $this->customer->id,
            'amount' => '1000.00',
            'payment_date' => '2026-05-20',
            'allocations' => [
                ['invoice_type' => 'SALES', 'sales_invoice_id' => $first->id, 'amount' => '300.00'],
                ['invoice_type' => 'SALES', 'sales_invoice_id' => $second->id, 'amount' => '700.00'],
            ],
        ], $this->user->id);

        $this->postPayment($payment);

        $this->assertSame('0.00', $first->fresh()->remaining);
        $this->assertSame('0.00', $second->fresh()->remaining);
    }

    // ------------------------------------------------------------ business rules

    public function test_an_allocation_may_not_exceed_what_the_invoice_still_owes(): void
    {
        $invoice = $this->salesInvoice('1000.00');
        $payment = $this->customerPayment('5000.00', $invoice, allocate: '5000.00');

        $this->expectException(BusinessRuleException::class);

        try {
            $this->postPayment($payment);
        } finally {
            $this->assertSame('0.00', $invoice->fresh()->paid_amount);
            $this->assertSame(DocumentStatus::DRAFT, $payment->fresh()->status);
        }
    }

    public function test_the_payment_amount_must_equal_the_sum_of_its_allocations(): void
    {
        $invoice = $this->salesInvoice('1000.00');
        $payment = $this->customerPayment('900.00', $invoice, allocate: '400.00');

        $this->expectException(BusinessRuleException::class);

        try {
            $this->postPayment($payment);
        } finally {
            // The mismatch is caught inside the transaction, so the partial
            // invoice update it had already applied is rolled back.
            $this->assertSame('0.00', $invoice->fresh()->paid_amount);
            $this->assertSame('1000.00', $invoice->fresh()->remaining);
        }
    }

    /**
     * Money can arrive before the invoice does — a repair deposit taken at the
     * counter. It posts unallocated, credits the account, and waits there to be
     * matched against an invoice later. (The payments API still demands an
     * allocation; only the service allows this, for the repair flow.)
     */
    public function test_an_unallocated_payment_posts_as_credit_on_the_account(): void
    {
        $payment = $this->payments->saveDraft(new Payment, [
            'payment_type' => PaymentType::CUSTOMER->value,
            'customer_id' => $this->customer->id,
            'amount' => '100.00',
            'payment_date' => '2026-05-20',
            'allocations' => [],
        ], $this->user->id);

        $posted = $this->postPayment($payment);

        $this->assertSame(DocumentStatus::POSTED, $posted->status);
        $this->assertNotNull($posted->reference_no);
        $this->assertDatabaseHas('customer_transactions', [
            'customer_id' => $this->customer->id,
            'type' => TransactionType::CUSTOMER_PAYMENT->value,
            'reference_id' => $posted->id,
            'credit' => '100.00',
        ]);
    }

    public function test_credit_on_account_can_be_matched_against_an_invoice_later(): void
    {
        $payment = $this->payments->saveDraft(new Payment, [
            'payment_type' => PaymentType::CUSTOMER->value,
            'customer_id' => $this->customer->id,
            'amount' => '400.00',
            'payment_date' => '2026-05-20',
            'allocations' => [],
        ], $this->user->id);
        $posted = $this->postPayment($payment);

        $invoice = $this->salesInvoice('1000.00');

        $this->payments->applyCredit($posted, $invoice, '400.00');

        $invoice->refresh();
        $this->assertSame('400.00', $invoice->paid_amount);
        $this->assertSame('600.00', $invoice->remaining);
        $this->assertSame('0.00', $this->payments->unallocated($posted->fresh())->toDecimal());

        // The credit was already in the ledger when the deposit posted; matching
        // it must not credit the customer a second time.
        $this->assertSame(1, CustomerTransaction::query()
            ->where('reference_id', $posted->id)
            ->where('reference_type', Payment::class)
            ->count());
    }

    public function test_credit_cannot_be_matched_beyond_what_is_left_on_the_payment(): void
    {
        $payment = $this->payments->saveDraft(new Payment, [
            'payment_type' => PaymentType::CUSTOMER->value,
            'customer_id' => $this->customer->id,
            'amount' => '100.00',
            'payment_date' => '2026-05-20',
            'allocations' => [],
        ], $this->user->id);
        $posted = $this->postPayment($payment);
        $invoice = $this->salesInvoice('1000.00');

        $this->expectException(BusinessRuleException::class);

        $this->payments->applyCredit($posted, $invoice, '150.00');
    }

    public function test_a_draft_invoice_cannot_be_settled(): void
    {
        $draft = $this->invoices->saveSalesDraft(new SalesInvoice, [
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-05-10',
            'items' => [$this->line('10', '100.00')],
        ], $this->user->id);

        $payment = $this->customerPayment('100.00', $draft);

        $this->expectException(BusinessRuleException::class);

        $this->postPayment($payment);
    }

    public function test_a_customer_payment_cannot_settle_another_customers_invoice(): void
    {
        $invoice = $this->salesInvoice('1000.00');

        $other = Customer::factory()->create();
        $payment = $this->payments->saveDraft(new Payment, [
            'payment_type' => PaymentType::CUSTOMER->value,
            'customer_id' => $other->id,
            'amount' => '100.00',
            'payment_date' => '2026-05-20',
            'allocations' => [
                ['invoice_type' => 'SALES', 'sales_invoice_id' => $invoice->id, 'amount' => '100.00'],
            ],
        ], $this->user->id);

        $this->expectException(BusinessRuleException::class);

        $this->postPayment($payment);
    }

    public function test_a_customer_payment_cannot_settle_a_purchase_invoice(): void
    {
        $purchase = $this->purchaseInvoice('500.00');

        $payment = $this->payments->saveDraft(new Payment, [
            'payment_type' => PaymentType::CUSTOMER->value,
            'customer_id' => $this->customer->id,
            'amount' => '100.00',
            'payment_date' => '2026-05-20',
            'allocations' => [
                ['invoice_type' => 'PURCHASE', 'purchase_invoice_id' => $purchase->id, 'amount' => '100.00'],
            ],
        ], $this->user->id);

        $this->expectException(BusinessRuleException::class);

        $this->postPayment($payment);
    }

    // ------------------------------------------------------- supplier payments

    public function test_a_supplier_payment_debits_the_supplier_ledger(): void
    {
        $purchase = $this->purchaseInvoice('500.00');

        $payment = $this->payments->saveDraft(new Payment, [
            'payment_type' => PaymentType::SUPPLIER->value,
            'supplier_id' => $this->supplier->id,
            'amount' => '200.00',
            'payment_date' => '2026-05-20',
            'allocations' => [
                ['invoice_type' => 'PURCHASE', 'purchase_invoice_id' => $purchase->id, 'amount' => '200.00'],
            ],
        ], $this->user->id);

        $this->postPayment($payment);

        $txn = SupplierTransaction::query()->where('type', TransactionType::SUPPLIER_PAYMENT)->sole();
        $this->assertSame('200.00', $txn->debit);
        $this->assertSame('0.00', $txn->credit);

        $this->assertSame('300.00', $purchase->fresh()->remaining);
    }

    // -------------------------------------------------------------- cancelling

    public function test_cancelling_a_payment_gives_the_balance_back(): void
    {
        $invoice = $this->salesInvoice('1000.00');
        $payment = $this->postPayment($this->customerPayment('400.00', $invoice));

        $this->payments->cancel($payment, $this->user->id, 'خطأ');

        $invoice->refresh();
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('1000.00', $invoice->remaining);
        $this->assertSame(DocumentStatus::CANCELLED, $payment->fresh()->status);
    }

    public function test_cancelling_a_payment_reverses_its_ledger_row(): void
    {
        $invoice = $this->salesInvoice('1000.00');
        $payment = $this->postPayment($this->customerPayment('400.00', $invoice));

        $this->payments->cancel($payment, $this->user->id, 'خطأ');

        // Back to just the invoice's own debit.
        $net = CustomerTransaction::query()->sum('debit') - CustomerTransaction::query()->sum('credit');
        $this->assertEquals(1000, $net);
        $this->assertSame(1, CustomerTransaction::query()->where('type', TransactionType::REVERSAL)->count());
    }

    public function test_a_posted_payment_cannot_be_posted_twice(): void
    {
        $invoice = $this->salesInvoice('1000.00');
        $payment = $this->postPayment($this->customerPayment('400.00', $invoice));

        $this->expectException(DocumentLockedException::class);

        $this->payments->post($payment, $this->user->id);
    }

    public function test_a_cancelled_invoice_with_payments_is_refused(): void
    {
        $invoice = $this->salesInvoice('1000.00');
        $this->postPayment($this->customerPayment('400.00', $invoice));

        // Cancelling an invoice that money has been received against would
        // strand the payment, so it is blocked until the payment is undone.
        $this->expectException(BusinessRuleException::class);

        $this->invoices->cancelSales($invoice->fresh(), $this->user->id, 'إلغاء');
    }

    // ------------------------------------------------------------------ helpers

    protected function salesInvoice(string $total): SalesInvoice
    {
        $draft = $this->invoices->saveSalesDraft(new SalesInvoice, [
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-05-10',
            'items' => [$this->line('1', $total)],
        ], $this->user->id);

        return $this->invoices->postSales($draft, $this->user->id);
    }

    protected function purchaseInvoice(string $total): PurchaseInvoice
    {
        $draft = $this->invoices->savePurchaseDraft(new PurchaseInvoice, [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => '2026-05-10',
            'items' => [[
                'material_id' => $this->material->id,
                'quantity' => '1',
                'unit_price' => $total,
            ]],
        ], $this->user->id);

        return $this->invoices->postPurchase($draft, $this->user->id);
    }

    protected function customerPayment(string $amount, SalesInvoice $invoice, ?string $allocate = null): Payment
    {
        return $this->payments->saveDraft(new Payment, [
            'payment_type' => PaymentType::CUSTOMER->value,
            'customer_id' => $this->customer->id,
            'amount' => $amount,
            'payment_date' => '2026-05-20',
            'allocations' => [
                ['invoice_type' => 'SALES', 'sales_invoice_id' => $invoice->id, 'amount' => $allocate ?? $amount],
            ],
        ], $this->user->id);
    }

    protected function postPayment(Payment $payment): Payment
    {
        return $this->payments->post($payment, $this->user->id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function line(string $quantity, string $price): array
    {
        return [
            'line_type' => LineType::MATERIAL,
            'material_id' => $this->material->id,
            'quantity' => $quantity,
            'unit_price' => $price,
        ];
    }
}
