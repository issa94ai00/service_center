<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\LineType;
use App\Enums\MovementDirection;
use App\Enums\MovementType;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\DocumentLockedException;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Material;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\Service;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DRAFT -> POSTED -> CANCELLED for a sales invoice, and what each transition is
 * required to do to stock, the customer ledger and the document totals.
 */
class SalesInvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoices;

    protected User $user;

    protected Customer $customer;

    protected Material $material;

    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Production seeds the numbering prefixes; without them documents come
        // out numbered "000001" instead of "SL-000001".
        Setting::query()->create([
            'key' => 'invoice_prefix', 'value' => 'SL-', 'group' => 'numbers', 'type' => 'string',
        ]);
        Setting::query()->create([
            'key' => 'payment_prefix', 'value' => 'PAY-', 'group' => 'numbers', 'type' => 'string',
        ]);

        $this->invoices = app(InvoiceService::class);
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->material = Material::factory()->inStock('100.000')->create();
        $this->service = Service::factory()->create();
    }

    // ------------------------------------------------------------------ draft

    public function test_a_draft_is_created_with_computed_totals_and_no_number(): void
    {
        $invoice = $this->draft([
            $this->materialLine(quantity: '10', price: '20.00'),
            $this->serviceLine(quantity: '100', price: '5.00'),
        ]);

        $this->assertSame(DocumentStatus::DRAFT, $invoice->status);
        $this->assertNull($invoice->invoice_no);
        $this->assertSame('700.00', $invoice->subtotal);
        $this->assertSame('700.00', $invoice->total);
        $this->assertSame('700.00', $invoice->remaining);
        $this->assertCount(2, $invoice->items()->get());
    }

    public function test_a_draft_moves_no_stock_and_writes_no_ledger_row(): void
    {
        $this->draft([$this->materialLine(quantity: '10', price: '20.00')]);

        $this->assertSame('100.000', $this->material->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, CustomerTransaction::query()->count());
    }

    public function test_a_line_may_not_total_less_than_zero(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->draft([
            $this->materialLine(quantity: '1', price: '10.00') + ['discount_amount' => '50.00'],
        ]);
    }

    // ----------------------------------------------------------------- posting

    public function test_posting_assigns_a_number_and_locks_the_document(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->materialLine('10', '20.00')]));

        $this->assertSame(DocumentStatus::POSTED, $invoice->status);
        $this->assertSame('SL-000001', $invoice->invoice_no);
        $this->assertNotNull($invoice->posted_at);
        $this->assertSame($this->user->id, $invoice->posted_by);
    }

    public function test_posting_decrements_stock_and_records_an_out_movement(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->materialLine('20', '20.00')]));

        $this->assertSame('80.000', $this->material->fresh()->stock_quantity);

        $movement = StockMovement::query()->sole();
        $this->assertSame(MovementType::SALE, $movement->type);
        $this->assertSame(MovementDirection::OUT, $movement->direction);
        $this->assertSame('20.000', $movement->quantity);
        $this->assertSame($invoice->id, $movement->source_id);
        $this->assertSame('SL-000001', $movement->reference_no);
    }

    public function test_a_service_line_moves_no_stock(): void
    {
        $this->postInvoice($this->draft([$this->serviceLine('100', '5.00')]));

        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame('100.000', $this->material->fresh()->stock_quantity);
    }

    public function test_posting_debits_the_customer_ledger_for_the_invoice_total(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->materialLine('10', '20.00')]));

        $txn = CustomerTransaction::query()->sole();
        $this->assertSame($this->customer->id, $txn->customer_id);
        $this->assertSame(TransactionType::SALES_INVOICE, $txn->type);
        $this->assertSame('200.00', $txn->debit);
        $this->assertSame('0.00', $txn->credit);
        $this->assertSame(SalesInvoice::class, $txn->reference_type);
        $this->assertSame($invoice->id, $txn->reference_id);
    }

    public function test_a_percentage_discount_is_applied_to_the_subtotal(): void
    {
        $invoice = $this->postInvoice($this->draft(
            [$this->materialLine('10', '100.00')],
            discountType: 'PERCENT',
            discountValue: '10',
        ));

        $this->assertSame('1000.00', $invoice->subtotal);
        $this->assertSame('100.00', $invoice->discount_amount);
        $this->assertSame('900.00', $invoice->total);
        $this->assertSame('900.00', $invoice->remaining);
        $this->assertSame('900.00', CustomerTransaction::query()->sole()->debit);
    }

    public function test_a_fixed_discount_is_subtracted_from_the_subtotal(): void
    {
        $invoice = $this->postInvoice($this->draft(
            [$this->materialLine('10', '100.00')],
            discountType: 'FIXED',
            discountValue: '250.50',
        ));

        $this->assertSame('250.50', $invoice->discount_amount);
        $this->assertSame('749.50', $invoice->total);
    }

    public function test_a_discount_may_not_exceed_the_subtotal(): void
    {
        // Rejected as the draft is built, before anything is persisted.
        $this->expectException(BusinessRuleException::class);

        $this->draft(
            [$this->materialLine('1', '100.00')],
            discountType: 'FIXED',
            discountValue: '500.00',
        );
    }

    public function test_a_discount_is_re_validated_when_the_document_is_posted(): void
    {
        $invoice = $this->draft([$this->materialLine('1', '100.00')]);

        // Totals are recomputed from the stored items on post, so tampering
        // with the persisted discount cannot slip past the draft-time check.
        $invoice->forceFill(['discount_type' => 'FIXED', 'discount_value' => '500.00'])->save();

        $this->expectException(BusinessRuleException::class);

        $this->postInvoice($invoice->fresh());
    }

    public function test_posting_is_refused_when_stock_is_short(): void
    {
        $invoice = $this->draft([$this->materialLine('500', '20.00')]);

        $this->expectException(InsufficientStockException::class);

        try {
            $this->postInvoice($invoice);
        } finally {
            // The whole transition rolls back: no number, no movement, no ledger.
            $this->assertSame(DocumentStatus::DRAFT, $invoice->fresh()->status);
            $this->assertSame('100.000', $this->material->fresh()->stock_quantity);
            $this->assertSame(0, StockMovement::query()->count());
            $this->assertSame(0, CustomerTransaction::query()->count());
        }
    }

    public function test_a_posted_invoice_cannot_be_posted_again(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->materialLine('10', '20.00')]));

        $this->expectException(DocumentLockedException::class);

        $this->invoices->postSales($invoice, $this->user->id);
    }

    public function test_a_posted_invoice_cannot_be_deleted(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->materialLine('10', '20.00')]));

        $this->expectException(DocumentLockedException::class);

        $this->invoices->deleteDraft($invoice);
    }

    public function test_numbers_are_sequential_across_invoices(): void
    {
        $first = $this->postInvoice($this->draft([$this->materialLine('1', '10.00')]));
        $second = $this->postInvoice($this->draft([$this->materialLine('1', '10.00')]));

        $this->assertSame('SL-000001', $first->invoice_no);
        $this->assertSame('SL-000002', $second->invoice_no);
    }

    // -------------------------------------------------------------- cancelling

    public function test_cancelling_returns_the_stock_it_took(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->materialLine('20', '20.00')]));
        $this->assertSame('80.000', $this->material->fresh()->stock_quantity);

        $this->invoices->cancelSales($invoice, $this->user->id, 'خطأ في الإدخال');

        $this->assertSame('100.000', $this->material->fresh()->stock_quantity);
    }

    public function test_cancelling_writes_a_reversing_movement_and_keeps_the_original(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->materialLine('20', '20.00')]));

        $this->invoices->cancelSales($invoice, $this->user->id, 'خطأ في الإدخال');

        // History is never rewritten: the original OUT movement survives and a
        // compensating IN movement is appended beside it.
        $this->assertSame(2, StockMovement::query()->count());

        $reversal = StockMovement::query()->whereNotNull('cancels_id')->sole();
        $this->assertSame(MovementDirection::IN, $reversal->direction);
        $this->assertSame(MovementType::SALE_RETURN, $reversal->type);
        $this->assertSame('20.000', $reversal->quantity);
        $this->assertSame('خطأ في الإدخال', $reversal->cancelled_reason);
    }

    public function test_cancelling_credits_the_ledger_back_to_zero(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->materialLine('10', '20.00')]));

        $this->invoices->cancelSales($invoice, $this->user->id, 'إلغاء');

        $this->assertSame(2, CustomerTransaction::query()->count());

        $net = CustomerTransaction::query()->sum('debit') - CustomerTransaction::query()->sum('credit');
        $this->assertEquals(0, $net, 'A cancelled invoice must leave no balance behind.');
    }

    public function test_cancelling_marks_the_document_cancelled(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->materialLine('10', '20.00')]));

        $cancelled = $this->invoices->cancelSales($invoice, $this->user->id, 'إلغاء');

        $this->assertSame(DocumentStatus::CANCELLED, $cancelled->status);
        $this->assertSame($this->user->id, $cancelled->cancelled_by);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_a_draft_cannot_be_cancelled(): void
    {
        $invoice = $this->draft([$this->materialLine('10', '20.00')]);

        $this->expectException(DocumentLockedException::class);

        $this->invoices->cancelSales($invoice, $this->user->id, 'إلغاء');
    }

    public function test_a_cancelled_invoice_cannot_be_cancelled_twice(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->materialLine('10', '20.00')]));
        $this->invoices->cancelSales($invoice, $this->user->id, 'إلغاء');

        $this->expectException(DocumentLockedException::class);

        $this->invoices->cancelSales($invoice->fresh(), $this->user->id, 'مرة أخرى');
    }

    // ------------------------------------------------------ paid on posting

    public function test_posting_with_a_paid_amount_records_an_allocated_receipt(): void
    {
        $invoice = $this->invoices->postSalesAndCollect(
            $this->draft([$this->materialLine('10', '20.00')]),
            $this->user->id,
            '200.00',
            ['payment_method' => 'CASH'],
        );

        $this->assertSame('200.00', $invoice->paid_amount);
        $this->assertSame('0.00', $invoice->remaining);

        $payment = Payment::query()->sole();
        $this->assertSame(DocumentStatus::POSTED, $payment->status);
        $this->assertSame('200.00', $payment->amount);
        $this->assertSame($this->customer->id, $payment->customer_id);
        // بتاريخ الفاتورة لا بتاريخ اليوم، وإلّا سبق القبضُ فاتورته في الكشف.
        $this->assertSame('2026-05-10', $payment->payment_date->format('Y-m-d'));

        // السند مخصَّص على الفاتورة، لا مبلغ عائم على حساب العميل.
        $allocation = $payment->allocations()->sole();
        $this->assertSame($invoice->id, $allocation->sales_invoice_id);
        $this->assertSame('200.00', $allocation->amount);

        // الفاتورة دَيناً والسند سداداً: الصافي على العميل صفر.
        $this->assertEquals(200, CustomerTransaction::query()->sum('debit'));
        $this->assertEquals(200, CustomerTransaction::query()->sum('credit'));
    }

    public function test_a_partial_payment_leaves_the_rest_owed(): void
    {
        $invoice = $this->invoices->postSalesAndCollect(
            $this->draft([$this->materialLine('10', '20.00')]),
            $this->user->id,
            '50.00',
        );

        $this->assertSame('50.00', $invoice->paid_amount);
        $this->assertSame('150.00', $invoice->remaining);
    }

    public function test_posting_without_a_paid_amount_leaves_the_invoice_unpaid(): void
    {
        $invoice = $this->invoices->postSalesAndCollect(
            $this->draft([$this->materialLine('10', '20.00')]),
            $this->user->id,
        );

        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('200.00', $invoice->remaining);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_paid_amount_above_the_total_is_refused_and_posts_nothing(): void
    {
        $draft = $this->draft([$this->materialLine('10', '20.00')]);

        try {
            $this->invoices->postSalesAndCollect($draft, $this->user->id, '250.00');
            $this->fail('Collecting more than the invoice total should have been refused.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('collect_amount', $e->field);
        }

        // الترحيل والقبض معاملة واحدة: سند مرفوض لا يترك فاتورة مرحَّلة خلفه،
        // ولا مخزوناً مخصوماً على بيع لم يتمّ.
        $draft->refresh();
        $this->assertSame(DocumentStatus::DRAFT, $draft->status);
        $this->assertNull($draft->invoice_no);
        $this->assertSame('100.000', $this->material->fresh()->stock_quantity);
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, CustomerTransaction::query()->count());
    }

    /**
     * صلاحية ترحيل فاتورة ليست صلاحية قبض مال: بائع يرحّل ولا يملك السندات
     * يجب أن يُردّ عند هذا الباب كما يُردّ في شاشة السندات نفسها.
     */
    public function test_collecting_on_post_requires_the_receipt_permissions(): void
    {
        $draft = $this->draft([$this->materialLine('10', '20.00')]);

        $this->actingAs($this->userWithPermissions(['sales.view', 'sales.create', 'sales.post']));

        $this->postJson("/api/sales/{$draft->id}/post", ['collect_amount' => '200.00'])
            ->assertForbidden();

        $draft->refresh();
        $this->assertSame(DocumentStatus::DRAFT, $draft->status);
        $this->assertSame(0, Payment::query()->count());

        // ومن يملكهما يمرّ، والفاتورة تخرج مسدَّدة.
        $this->actingAs($this->userWithPermissions([
            'sales.view', 'sales.create', 'sales.post', 'payments.create', 'payments.confirm',
        ]));

        $this->postJson("/api/sales/{$draft->id}/post", ['collect_amount' => '200.00'])
            ->assertOk()
            ->assertJsonPath('data.paid_amount', '200.00')
            ->assertJsonPath('data.remaining', '0.00');
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param  array<int, string>  $slugs
     */
    protected function userWithPermissions(array $slugs): User
    {
        $role = Role::query()->create(['name' => 'دور اختبار '.uniqid(), 'slug' => 'test-'.uniqid()]);

        foreach ($slugs as $slug) {
            $role->permissions()->attach(Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => explode('.', $slug)[0]]
            ));
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    protected function draft(array $lines, string $discountType = 'NONE', string $discountValue = '0'): SalesInvoice
    {
        return $this->invoices->saveSalesDraft(new SalesInvoice, [
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-05-10',
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'items' => $lines,
        ], $this->user->id);
    }

    protected function postInvoice(SalesInvoice $invoice): SalesInvoice
    {
        return $this->invoices->postSales($invoice, $this->user->id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function materialLine(string $quantity, string $price): array
    {
        return [
            'line_type' => LineType::MATERIAL,
            'material_id' => $this->material->id,
            'quantity' => $quantity,
            'unit_price' => $price,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serviceLine(string $quantity, string $price): array
    {
        return [
            'line_type' => LineType::SERVICE,
            'service_id' => $this->service->id,
            'quantity' => $quantity,
            'unit_price' => $price,
        ];
    }
}
