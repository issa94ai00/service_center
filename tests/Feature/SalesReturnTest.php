<?php

namespace Tests\Feature;

use App\Enums\CostMethod;
use App\Enums\DocumentStatus;
use App\Enums\LineType;
use App\Enums\MovementDirection;
use App\Enums\MovementSourceType;
use App\Enums\RepairStatus;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Material;
use App\Models\Permission;
use App\Models\Repair;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Service;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\FifoCostService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ReportService;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A sales return: partial or full, what it does to stock, the customer's
 * ledger and the invoice's open balance, and how cancelling it undoes that.
 */
class SalesReturnTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoices;

    protected ReturnService $returns;

    protected User $user;

    protected Customer $customer;

    protected Material $material;

    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoices = app(InvoiceService::class);
        $this->returns = app(ReturnService::class);
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->material = Material::factory()->inStock('100.000')->create(['cost_price' => '12.00']);
        $this->service = Service::factory()->create();
    }

    public function test_a_partial_return_restocks_credits_the_customer_and_closes_part_of_the_invoice(): void
    {
        $invoice = $this->postedInvoice([$this->materialLine('10', '20.00')]);

        $return = $this->returnLines($invoice, [[$invoice->items[0]->id, '3']]);

        $this->assertSame(DocumentStatus::POSTED, $return->status);
        $this->assertStringStartsWith('SRT-', $return->return_no);
        $this->assertSame('60.00', $return->total);
        $this->assertSame('60.00', $return->applied_amount);
        $this->assertSame('36.00', $return->cost_amount);

        $this->assertSame('93.000', $this->material->fresh()->stock_quantity);

        $invoice->refresh();
        $this->assertSame('60.00', $invoice->returned_amount);
        $this->assertSame('140.00', $invoice->remaining);
        $this->assertSame('200.00', $invoice->total, 'the invoice itself is never rewritten');

        $movement = StockMovement::query()->where('source_type', MovementSourceType::SALES_RETURN)->sole();
        $this->assertSame(MovementDirection::IN, $movement->direction);
        $this->assertSame('3.000', $movement->quantity);

        $credit = CustomerTransaction::query()->where('type', TransactionType::SALES_RETURN)->sole();
        $this->assertSame('60.00', $credit->credit);
        $this->assertSame(SalesReturn::class, $credit->reference_type);
    }

    public function test_quantities_cannot_exceed_what_is_left_to_return(): void
    {
        $invoice = $this->postedInvoice([$this->materialLine('5', '10.00')]);
        $lineId = $invoice->items[0]->id;

        $this->returnLines($invoice, [[$lineId, '4']]);

        $this->expectException(BusinessRuleException::class);
        $this->returnLines($invoice->fresh(), [[$lineId, '2']]);
    }

    public function test_successive_partial_returns_add_up_to_the_invoice_exactly(): void
    {
        // 3 × 10.00 with a 10.00 invoice discount: thirds do not divide evenly
        // into cents, so only the closing return can square the account.
        $invoice = $this->postedInvoice([$this->materialLine('3', '10.00')], 'FIXED', '10.00');
        $lineId = $invoice->items[0]->id;

        $a = $this->returnLines($invoice, [[$lineId, '1']]);
        $b = $this->returnLines($invoice->fresh(), [[$lineId, '1']]);
        $c = $this->returnLines($invoice->fresh(), [[$lineId, '1']]);

        $sum = bcadd(bcadd($a->total, $b->total, 2), $c->total, 2);
        $this->assertSame('20.00', $sum);
        $this->assertSame('0.00', $invoice->fresh()->remaining);
    }

    public function test_a_return_on_a_paid_invoice_leaves_credit_on_the_account_by_default(): void
    {
        $invoice = $this->postedInvoice([$this->materialLine('2', '50.00')], collect: '100.00');

        $return = $this->returnLines($invoice, [[$invoice->items[0]->id, '1']]);

        $this->assertSame('0.00', $return->applied_amount);
        $this->assertSame('0.00', $return->refunded_amount);
        $this->assertSame('0.00', $invoice->fresh()->remaining);
        $this->assertSame('-50.00', $this->customerBalance(), 'the customer is owed 50 on account');
    }

    public function test_a_cash_refund_pays_out_only_what_exceeded_the_open_balance(): void
    {
        // 100 invoiced, 30 paid: returning everything closes the 70 still open
        // and hands back the 30 that was actually paid.
        $invoice = $this->postedInvoice([$this->materialLine('2', '50.00')], collect: '30.00');

        $return = $this->returnLines($invoice, [[$invoice->items[0]->id, '2']], settlement: 'REFUND');

        $this->assertSame('100.00', $return->total);
        $this->assertSame('70.00', $return->applied_amount);
        $this->assertSame('30.00', $return->refunded_amount);
        $this->assertSame('CASH', $return->refund_method);

        $refund = CustomerTransaction::query()->where('type', TransactionType::CUSTOMER_REFUND)->sole();
        $this->assertSame('30.00', $refund->debit);
        $this->assertSame('0.00', $this->customerBalance());
    }

    public function test_service_lines_can_be_returned_without_touching_stock(): void
    {
        $invoice = $this->postedInvoice([$this->materialLine('1', '10.00'), $this->serviceLine('1', '40.00')]);
        $serviceLine = $invoice->items->firstWhere('line_type', LineType::SERVICE);

        $return = $this->returnLines($invoice, [[$serviceLine->id, '1']]);

        $this->assertSame('40.00', $return->total);
        $this->assertSame('0.00', $return->cost_amount);
        $this->assertSame(0, StockMovement::query()->where('source_type', MovementSourceType::SALES_RETURN)->count());
    }

    public function test_cancelling_a_return_reverses_stock_ledger_and_invoice_balance(): void
    {
        $invoice = $this->postedInvoice([$this->materialLine('10', '20.00')], collect: '150.00');
        $return = $this->returnLines($invoice, [[$invoice->items[0]->id, '5']], settlement: 'REFUND');

        // 100 returned against 50 open: 50 closes the invoice, 50 goes back in cash.
        $this->assertSame('50.00', $return->applied_amount);
        $this->assertSame('50.00', $return->refunded_amount);

        $cancelled = $this->returns->cancelSalesReturn($return, $this->user->id, 'خطأ في الإدخال');

        $this->assertSame(DocumentStatus::CANCELLED, $cancelled->status);
        $this->assertSame('90.000', $this->material->fresh()->stock_quantity);
        $this->assertSame('50.00', $invoice->fresh()->remaining);
        $this->assertSame('0.00', $invoice->fresh()->returned_amount);
        $this->assertSame('50.00', $this->customerBalance());

        // …and the full quantity can be returned again.
        $again = $this->returnLines($invoice->fresh(), [[$invoice->items[0]->id, '10']]);
        $this->assertSame('200.00', $again->total);
    }

    public function test_cancelling_a_return_whose_goods_were_resold_is_refused_under_strict_stock(): void
    {
        $material = Material::factory()->inStock('2.000')->create();
        $invoice = $this->postedInvoice([[
            'line_type' => LineType::MATERIAL, 'material_id' => $material->id, 'quantity' => '2', 'unit_price' => '5.00',
        ]]);
        $return = $this->returnLines($invoice, [[$invoice->items[0]->id, '2']]);
        $this->postedInvoice([[
            'line_type' => LineType::MATERIAL, 'material_id' => $material->id, 'quantity' => '2', 'unit_price' => '5.00',
        ]]);

        $this->expectException(InsufficientStockException::class);
        $this->returns->cancelSalesReturn($return, $this->user->id, 'تجربة');
    }

    public function test_an_invoice_with_a_standing_return_cannot_be_cancelled(): void
    {
        $invoice = $this->postedInvoice([$this->materialLine('2', '10.00')]);
        $this->returnLines($invoice, [[$invoice->items[0]->id, '1']]);

        $this->expectException(BusinessRuleException::class);
        $this->invoices->cancelSales($invoice->fresh(), $this->user->id, 'إلغاء');
    }

    public function test_a_payment_after_a_return_only_covers_what_is_still_open(): void
    {
        $invoice = $this->postedInvoice([$this->materialLine('10', '10.00')]);
        $this->returnLines($invoice, [[$invoice->items[0]->id, '4']]);

        $payment = app(PaymentService::class)->collectOnSalesInvoice($invoice->fresh(), '60.00', [], $this->user->id);

        $invoice->refresh();
        $this->assertSame('60.00', $invoice->paid_amount);
        $this->assertSame('0.00', $invoice->remaining);

        app(PaymentService::class)->cancel($payment, $this->user->id, 'خطأ');
        $this->assertSame('60.00', $invoice->fresh()->remaining);
    }

    public function test_fifo_goods_come_back_at_the_cost_they_left_at(): void
    {
        $material = Material::factory()->inStock('0.000')->create(['cost_method' => CostMethod::FIFO, 'cost_price' => '0.00']);
        app(FifoCostService::class)->receive($material, '5.000', '8.00');
        $material->update(['stock_quantity' => '5.000']);

        $invoice = $this->postedInvoice([[
            'line_type' => LineType::MATERIAL, 'material_id' => $material->id, 'quantity' => '2', 'unit_price' => '20.00',
        ]]);
        $return = $this->returnLines($invoice, [[$invoice->items[0]->id, '2']]);

        $this->assertSame('16.00', $return->cost_amount);
        $this->assertSame('8.00', $material->fresh()->cost_price);
    }

    public function test_net_revenue_and_cogs_in_the_profit_report_exclude_returns(): void
    {
        $invoice = $this->postedInvoice([$this->materialLine('10', '20.00')]);
        $this->returnLines($invoice, [[$invoice->items[0]->id, '2']], date: '2026-05-10');

        $report = app(ReportService::class)->profitSummary('2026-05-01', '2026-05-31');

        $this->assertSame('200.00', $report['revenue']['gross_total']);
        $this->assertSame('40.00', $report['revenue']['returns']['total']);
        $this->assertSame('160.00', $report['revenue']['total']);
        $this->assertSame('96.00', $report['cogs']['total']);
    }

    public function test_repair_invoices_are_not_returned_from_here(): void
    {
        $invoice = $this->postedInvoice([$this->serviceLine('1', '10.00')]);
        $repair = Repair::query()->create([
            'customer_id' => $this->customer->id,
            'device_type' => 'هاتف',
            'reported_issue' => 'كسر شاشة',
            'status' => RepairStatus::DELIVERED,
            'received_at' => '2026-05-02 10:00:00',
        ]);
        $invoice->forceFill(['repair_id' => $repair->id])->saveQuietly();

        $this->expectException(BusinessRuleException::class);
        $this->returnLines($invoice->fresh(), [[$invoice->items[0]->id, '1']]);
    }

    // ------------------------------------------------------------------- http

    public function test_the_api_lists_returnable_lines_and_records_a_return(): void
    {
        $invoice = $this->postedInvoice([$this->materialLine('4', '25.00')]);
        $this->actingAs($this->userWithPermissions(['sales.view', 'sales.return']));

        $this->getJson("/api/sales/{$invoice->id}/returnable")
            ->assertOk()
            ->assertJsonPath('data.lines.0.returnable_quantity', '4.000')
            ->assertJsonPath('data.blocked_reason', null);

        $this->postJson("/api/sales/{$invoice->id}/returns", [
            'reason' => 'تالف',
            'items' => [['sales_invoice_item_id' => $invoice->items[0]->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('data.total', '25.00');

        $this->getJson("/api/sales/{$invoice->id}/returnable")
            ->assertJsonPath('data.lines.0.returnable_quantity', '3.000')
            ->assertJsonCount(1, 'data.returns');

        $this->getJson('/api/sales-returns')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/sales')->assertJsonPath('data.0.active_returns_count', 1);
    }

    public function test_a_cash_refund_needs_payment_rights(): void
    {
        $invoice = $this->postedInvoice([$this->materialLine('1', '10.00')], collect: '10.00');
        $this->actingAs($this->userWithPermissions(['sales.view', 'sales.return']));

        $this->postJson("/api/sales/{$invoice->id}/returns", [
            'reason' => 'تالف',
            'settlement' => 'REFUND',
            'items' => [['sales_invoice_item_id' => $invoice->items[0]->id, 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_returning_requires_the_return_permission(): void
    {
        $invoice = $this->postedInvoice([$this->materialLine('1', '10.00')]);
        $this->actingAs($this->userWithPermissions(['sales.view', 'sales.cancel']));

        $this->postJson("/api/sales/{$invoice->id}/returns", [
            'reason' => 'تالف',
            'items' => [['sales_invoice_item_id' => $invoice->items[0]->id, 'quantity' => 1]],
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------- helpers

    protected function postedInvoice(array $lines, string $discountType = 'NONE', string $discountValue = '0', string $collect = '0'): SalesInvoice
    {
        $draft = $this->invoices->saveSalesDraft(new SalesInvoice, [
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-05-10',
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'items' => $lines,
        ], $this->user->id);

        return $this->invoices->postSalesAndCollect($draft, $this->user->id, $collect)->load('items');
    }

    /**
     * @param  array<int, array{0: int, 1: string}>  $lines
     */
    protected function returnLines(SalesInvoice $invoice, array $lines, string $settlement = 'CREDIT', ?string $date = null): SalesReturn
    {
        return $this->returns->createSalesReturn($invoice, [
            'reason' => 'تالف',
            'settlement' => $settlement,
            'return_date' => $date,
            'items' => array_map(fn ($l) => ['sales_invoice_item_id' => $l[0], 'quantity' => $l[1]], $lines),
        ], $this->user->id);
    }

    protected function customerBalance(): string
    {
        $row = CustomerTransaction::query()->where('customer_id', $this->customer->id)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')->first();

        return number_format((float) $row->balance, 2, '.', '');
    }

    protected function materialLine(string $quantity, string $price): array
    {
        return ['line_type' => LineType::MATERIAL, 'material_id' => $this->material->id, 'quantity' => $quantity, 'unit_price' => $price];
    }

    protected function serviceLine(string $quantity, string $price): array
    {
        return ['line_type' => LineType::SERVICE, 'service_id' => $this->service->id, 'quantity' => $quantity, 'unit_price' => $price];
    }

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
}
