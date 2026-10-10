<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\RepairStatus;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Material;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Repair;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The money side of a repair, end to end: a deposit taken at the counter waits
 * on the customer's account, and the hand-over turns it into a settled invoice.
 */
class RepairSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->insert([
            ['key' => 'repair_receipt_prefix', 'value' => 'RPR-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'currency_symbol', 'value' => 'ل.س', 'group' => 'general', 'type' => 'string'],
            ['key' => 'invoice_prefix', 'value' => 'SL-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'payment_prefix', 'value' => 'PAY-', 'group' => 'numbers', 'type' => 'string'],
        ]);

        // repair_invoice_prefix (RS-) and repair_payment_prefix (RCV-) arrive
        // from their migrations; inserting them again here would collide.

        $this->customer = Customer::factory()->create();
    }

    // ------------------------------------------------------------------ intake

    public function test_a_deposit_taken_at_intake_posts_with_the_repair(): void
    {
        $this->actingAs($this->userWith(['repairs.create', 'repairs.settle']));

        $response = $this->postJson('/api/repairs', $this->intake(['deposit_amount' => '30000']))
            ->assertCreated();

        $repair = Repair::query()->firstOrFail();
        $payment = Payment::query()->firstOrFail();

        $this->assertSame($repair->id, $payment->repair_id);
        $this->assertSame(DocumentStatus::POSTED, $payment->status);
        $this->assertSame('30000.00', $payment->amount);
        $this->assertSame($this->customer->id, $payment->customer_id);
        // No invoice exists yet, so the money sits on the account as credit.
        $this->assertCount(0, $payment->allocations);

        $this->assertDatabaseHas('customer_transactions', [
            'customer_id' => $this->customer->id,
            'reference_id' => $payment->id,
            'credit' => '30000.00',
        ]);

        $response->assertJsonPath('settlement.paid', '30000.00')
            ->assertJsonPath('settlement.due', '50000.00')
            ->assertJsonPath('data.paid', '30000.00');
    }

    public function test_the_device_is_not_registered_when_its_deposit_cannot_post(): void
    {
        $this->actingAs($this->userWith(['repairs.create', 'repairs.settle']));

        $this->postJson('/api/repairs', $this->intake(['deposit_amount' => '-5']))
            ->assertStatus(422);

        $this->assertDatabaseCount('repairs', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_taking_a_deposit_needs_more_than_permission_to_receive_devices(): void
    {
        $this->actingAs($this->userWith(['repairs.create']));

        $this->postJson('/api/repairs', $this->intake(['deposit_amount' => '30000']))
            ->assertForbidden();

        $this->assertDatabaseCount('repairs', 0);
    }

    public function test_a_device_may_still_be_received_without_any_money(): void
    {
        $this->actingAs($this->userWith(['repairs.create']));

        $this->postJson('/api/repairs', $this->intake())->assertCreated();

        $this->assertDatabaseCount('repairs', 1);
        $this->assertDatabaseCount('payments', 0);
    }

    // ------------------------------------------------------------- collections

    public function test_a_later_deposit_is_added_to_the_account(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '10000'])
            ->assertOk()
            ->assertJsonPath('settlement.paid', '10000.00');

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '5000', 'payment_method' => 'BANK'])
            ->assertOk()
            ->assertJsonPath('settlement.paid', '15000.00')
            ->assertJsonPath('settlement.due', '65000.00');
    }

    public function test_collecting_money_requires_the_settle_permission(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.edit']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '10000'])
            ->assertForbidden();
    }

    public function test_nothing_can_be_collected_against_a_cancelled_repair(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair(['status' => RepairStatus::CANCELLED]);

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '10000'])
            ->assertStatus(409);
    }

    // -------------------------------------------------------------- settlement

    public function test_settling_invoices_the_repair_spends_the_deposit_and_collects_the_rest(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '30000'])->assertOk();

        $this->postJson("/api/repairs/{$repair->id}/settle", [
            'total' => '80000',
            'collect_amount' => '50000',
        ])
            ->assertOk()
            ->assertJsonPath('settlement.is_settled', true)
            ->assertJsonPath('settlement.paid', '80000.00')
            ->assertJsonPath('settlement.due', '0.00')
            ->assertJsonPath('settlement.invoice.remaining', '0.00');

        $invoice = SalesInvoice::query()->firstOrFail();
        $this->assertSame(DocumentStatus::POSTED, $invoice->status);
        $this->assertSame('80000.00', $invoice->total);
        $this->assertSame('80000.00', $invoice->paid_amount);
        // Settlements number on the repair series, not the counter sales one.
        $this->assertSame('RS-000001', $invoice->invoice_no);
        $this->assertSame($repair->id, $invoice->repair_id);

        $repair->refresh();
        $this->assertSame(RepairStatus::DELIVERED, $repair->status);
        $this->assertSame($invoice->id, $repair->sales_invoice_id);
        $this->assertSame('80000.00', $repair->final_cost);
        $this->assertNotNull($repair->settled_at);
        $this->assertNotNull($repair->delivered_at);

        // One debit for the invoice, one credit per receipt — and the deposit
        // is not credited a second time when it is matched to the invoice.
        $ledger = CustomerTransaction::query()->where('customer_id', $this->customer->id)->get();
        $this->assertEquals(80000, $ledger->sum('debit'));
        $this->assertEquals(80000, $ledger->sum('credit'));
        $this->assertCount(3, $ledger);
    }

    public function test_settling_on_credit_leaves_the_balance_owed_on_the_invoice(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '30000'])->assertOk();

        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])
            ->assertOk()
            ->assertJsonPath('settlement.due', '50000.00')
            ->assertJsonPath('settlement.invoice.remaining', '50000.00');

        $this->assertSame(RepairStatus::DELIVERED, $repair->fresh()->status);
    }

    public function test_the_rest_can_be_collected_after_the_device_has_gone(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])->assertOk();

        // Now it lands on the invoice rather than floating on the account.
        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '80000'])
            ->assertOk()
            ->assertJsonPath('settlement.invoice.remaining', '0.00');

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '1000'])
            ->assertStatus(409);
    }

    public function test_a_deposit_larger_than_the_bill_stays_as_credit_for_the_customer(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '100000'])->assertOk();

        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])
            ->assertOk()
            ->assertJsonPath('settlement.credit', '20000.00')
            ->assertJsonPath('settlement.invoice.remaining', '0.00');

        $payment = Payment::query()->firstOrFail();
        $this->assertEquals(80000, $payment->allocations()->sum('amount'));
    }

    public function test_a_repair_with_nothing_to_charge_is_delivered_without_an_invoice(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '0'])
            ->assertOk()
            ->assertJsonPath('settlement.is_settled', true)
            ->assertJsonPath('settlement.invoice', null);

        $this->assertDatabaseCount('sales_invoices', 0);
        $this->assertSame(RepairStatus::DELIVERED, $repair->fresh()->status);
    }

    public function test_collecting_more_than_the_invoice_owes_is_refused(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '30000'])->assertOk();

        $this->postJson("/api/repairs/{$repair->id}/settle", [
            'total' => '80000',
            'collect_amount' => '60000',
        ])->assertStatus(409);

        // The whole settlement rolled back: no invoice, and the device is still
        // on the bench rather than half handed over.
        $this->assertDatabaseCount('sales_invoices', 0);
        $this->assertSame(RepairStatus::RECEIVED, $repair->fresh()->status);
    }

    public function test_a_repair_cannot_be_settled_twice(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])->assertOk();
        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])->assertStatus(409);

        $this->assertDatabaseCount('sales_invoices', 1);
    }

    public function test_a_cancelled_repair_cannot_be_settled(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair(['status' => RepairStatus::CANCELLED]);

        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])
            ->assertStatus(409);
    }

    public function test_settling_requires_the_settle_permission(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.edit']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])
            ->assertForbidden();
    }

    public function test_the_settlement_summary_follows_the_view_permission(): void
    {
        $repair = $this->repair();

        $this->actingAs($this->userWith([]))
            ->getJson("/api/repairs/{$repair->id}/settlement")
            ->assertForbidden();

        $this->actingAs($this->userWith(['repairs.view']))
            ->getJson("/api/repairs/{$repair->id}/settlement")
            ->assertOk()
            ->assertJsonPath('data.chargeable_cost', '80000.00')
            ->assertJsonPath('data.can_settle', true);
    }

    public function test_cancelling_the_settlement_invoice_reopens_the_repair_for_settlement(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])->assertOk();

        SalesInvoice::query()->firstOrFail()->update(['status' => DocumentStatus::CANCELLED]);

        $this->getJson("/api/repairs/{$repair->id}/settlement")
            ->assertOk()
            ->assertJsonPath('data.is_settled', false)
            ->assertJsonPath('data.can_settle', true);
    }

    public function test_the_intake_receipt_shows_the_deposit_and_what_is_left(): void
    {
        Setting::query()->create([
            'key' => 'company_name', 'value' => 'مركز الهواتف', 'group' => 'general', 'type' => 'string',
        ]);

        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '30000'])->assertOk();

        $this->get("/api/pdf/repairs/{$repair->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_deposit_prints_as_an_unallocated_receipt(): void
    {
        Setting::query()->create([
            'key' => 'company_name', 'value' => 'مركز الهواتف', 'group' => 'general', 'type' => 'string',
        ]);
        Setting::query()->create([
            'key' => 'currency_name', 'value' => 'ليرة سورية', 'group' => 'general', 'type' => 'string',
        ]);

        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle', 'payments.create', 'payments.view']));
        $repair = $this->repair();

        $response = $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '30000'])->assertOk();

        $this->get("/api/pdf/payments/{$response->json('payment_id')}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    // -------------------------------------------------------- counter context

    public function test_the_intake_hands_back_the_deposit_receipt_to_print(): void
    {
        $this->actingAs($this->userWith(['repairs.create', 'repairs.settle']));

        $response = $this->postJson('/api/repairs', $this->intake(['deposit_amount' => '30000']))
            ->assertCreated();

        $payment = Payment::query()->firstOrFail();

        $response->assertJsonPath('payment_id', $payment->id);
    }

    public function test_an_intake_without_money_has_no_receipt_to_print(): void
    {
        $this->actingAs($this->userWith(['repairs.create', 'repairs.settle']));

        $this->postJson('/api/repairs', $this->intake())
            ->assertCreated()
            ->assertJsonPath('payment_id', null);
    }

    public function test_the_customer_account_shows_what_is_owed_before_a_deposit_is_agreed(): void
    {
        $this->actingAs($this->userWith(['repairs.create', 'repairs.settle']));

        // An older device of theirs, half paid for and still on the bench.
        $earlier = $this->repair(['estimated_cost' => '50000']);
        $this->postJson("/api/repairs/{$earlier->id}/deposit", ['amount' => '20000'])->assertOk();

        $this->getJson('/api/repairs/customer-account?customer_id='.$this->customer->id)
            ->assertOk()
            // The deposit sits on the account as credit until it is invoiced.
            ->assertJsonPath('data.balance', '-20000.00')
            ->assertJsonPath('data.open_repairs', 1)
            ->assertJsonPath('data.open_due', '30000.00');
    }

    public function test_a_settled_device_no_longer_counts_against_the_customer_account(): void
    {
        $this->actingAs($this->userWith(['repairs.create', 'repairs.settle']));

        $repair = $this->repair(['estimated_cost' => '50000']);
        $this->postJson("/api/repairs/{$repair->id}/settle", [
            'total' => '50000',
            'collect_amount' => '50000',
        ])->assertOk();

        $this->getJson('/api/repairs/customer-account?customer_id='.$this->customer->id)
            ->assertOk()
            ->assertJsonPath('data.balance', '0.00')
            ->assertJsonPath('data.open_repairs', 0)
            ->assertJsonPath('data.open_due', '0.00');
    }

    public function test_reading_a_customer_account_needs_the_settle_permission(): void
    {
        $this->actingAs($this->userWith(['repairs.create', 'repairs.view']));

        $this->getJson('/api/repairs/customer-account?customer_id='.$this->customer->id)
            ->assertForbidden();
    }

    // --------------------------------------------------- the maintenance series

    public function test_money_taken_against_a_device_numbers_on_the_maintenance_series(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '30000'])->assertOk();
        $this->postJson("/api/repairs/{$repair->id}/settle", [
            'total' => '80000',
            'collect_amount' => '50000',
        ])->assertOk();

        // Both receipts came off the maintenance counter, so both run on its
        // own series rather than sharing the counter-sales numbers.
        $this->assertSame(
            ['RCV-000001', 'RCV-000002'],
            Payment::query()->orderBy('id')->pluck('reference_no')->all(),
        );
        $this->assertSame('RS-000001', SalesInvoice::query()->value('invoice_no'));
    }

    public function test_the_two_receipt_series_run_independently(): void
    {
        $user = $this->userWith(['repairs.view', 'repairs.settle']);
        $this->actingAs($user);
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '10000'])->assertOk();

        // A receipt with no repair behind it draws the next counter number, and
        // the two counters advance without touching each other.
        $payments = app(PaymentService::class);
        $payments->post($payments->saveDraft(new Payment, [
            'payment_type' => 'CUSTOMER',
            'customer_id' => $this->customer->id,
            'amount' => '5000',
            'payment_method' => 'CASH',
            'allocations' => [],
        ], $user->id), $user->id);

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '7000'])->assertOk();

        $this->assertSame(
            ['RCV-000001', 'RCV-000002'],
            Payment::query()->whereNotNull('repair_id')->orderBy('id')->pluck('reference_no')->all(),
        );
        $this->assertSame('PAY-000001', Payment::query()->whereNull('repair_id')->value('reference_no'));
    }

    public function test_a_maintenance_receipt_is_flagged_in_the_payments_listing(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle', 'payments.create', 'payments.view']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/deposit", ['amount' => '10000'])->assertOk();

        $this->getJson('/api/payments?source=REPAIR')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_repair', true)
            ->assertJsonPath('data.0.repair.receipt_no', $repair->receipt_no);

        // The same listing filtered the other way must not carry it.
        $this->getJson('/api/payments?source=DIRECT')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_settlement_invoice_is_flagged_in_the_sales_listing(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle', 'sales.view']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])->assertOk();

        $this->getJson('/api/sales?source=REPAIR')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_repair', true)
            ->assertJsonPath('data.0.repair.receipt_no', $repair->receipt_no);

        $this->getJson('/api/sales?source=SALES')->assertOk()->assertJsonCount(0, 'data');
    }

    // ------------------------------------------------------- the itemised bill

    public function test_a_bill_of_labour_and_parts_prices_itself_and_moves_the_stock(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $material = Material::factory()->inStock('5')->create(['sale_price' => '35000']);
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/settle", [
            // The total is deliberately a lie: the lines are what counts.
            'total' => '1',
            'items' => [
                ['line_type' => 'SERVICE', 'description' => 'أجرة إصلاح شاشة', 'quantity' => 1, 'unit_price' => '40000'],
                ['line_type' => 'MATERIAL', 'material_id' => $material->id, 'quantity' => 2, 'unit_price' => '35000'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('settlement.invoice.total', '110000.00');

        $invoice = SalesInvoice::query()->firstOrFail();
        $this->assertSame('110000.00', $invoice->total);
        $this->assertSame('RS-000001', $invoice->invoice_no);
        $this->assertCount(2, $invoice->items);

        // The parts left with the device, so they left the shelf too.
        $this->assertSame('3.000', $material->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'material_id' => $material->id,
            'source_id' => $invoice->id,
            'reference_no' => 'RS-000001',
            'direction' => 'OUT',
        ]);

        $this->assertSame('110000.00', $repair->fresh()->final_cost);
    }

    public function test_the_technician_earns_on_the_labour_not_on_the_parts(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $material = Material::factory()->inStock('5')->create();
        $technician = Technician::factory()->create(['commission_rate' => '10.00']);
        $repair = $this->repair(['technician_id' => $technician->id]);

        $this->postJson("/api/repairs/{$repair->id}/settle", [
            'items' => [
                ['line_type' => 'SERVICE', 'quantity' => 1, 'unit_price' => '40000'],
                ['line_type' => 'MATERIAL', 'material_id' => $material->id, 'quantity' => 1, 'unit_price' => '60000'],
            ],
        ])->assertOk();

        // 10% of the 40,000 labour, not of the 100,000 the customer paid: the
        // spare part is resold goods, and its margin is the shop's.
        $this->assertSame('4000.00', $repair->fresh()->commission_amount);
        $this->assertDatabaseHas('technician_transactions', [
            'technician_id' => $technician->id,
            'reference_id' => $repair->id,
            'credit' => '4000.00',
        ]);
    }

    public function test_a_part_fitted_free_of_charge_still_leaves_the_shelf(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $material = Material::factory()->inStock('4')->create();
        $repair = $this->repair();

        // A warranty job: nothing to charge, but the part is gone all the same.
        $this->postJson("/api/repairs/{$repair->id}/settle", [
            'items' => [
                ['line_type' => 'MATERIAL', 'material_id' => $material->id, 'quantity' => 1, 'unit_price' => '0'],
            ],
        ])->assertOk()->assertJsonPath('settlement.invoice.total', '0.00');

        $this->assertSame('3.000', $material->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::query()->where('material_id', $material->id)->count());
    }

    public function test_a_bill_that_outruns_the_shelf_settles_nothing(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $material = Material::factory()->inStock('1')->create();
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/settle", [
            'items' => [
                ['line_type' => 'SERVICE', 'quantity' => 1, 'unit_price' => '40000'],
                ['line_type' => 'MATERIAL', 'material_id' => $material->id, 'quantity' => 3, 'unit_price' => '10000'],
            ],
        ])->assertStatus(409)->assertJsonPath('field', 'items');

        // The whole hand-over rolls back: no invoice, no movement, still open.
        $this->assertDatabaseCount('sales_invoices', 0);
        $this->assertSame('1.000', $material->fresh()->stock_quantity);
        $this->assertNull($repair->fresh()->settled_at);
        $this->assertSame(RepairStatus::RECEIVED, $repair->fresh()->status);
    }

    public function test_a_line_discounted_below_zero_is_refused(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/settle", [
            'items' => [
                ['line_type' => 'SERVICE', 'quantity' => 1, 'unit_price' => '10000', 'discount_amount' => '15000'],
            ],
        ])->assertStatus(409)->assertJsonPath('field', 'items');

        $this->assertDatabaseCount('sales_invoices', 0);
    }

    public function test_a_part_line_without_a_material_is_refused(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/settle", [
            'items' => [['line_type' => 'MATERIAL', 'quantity' => 1, 'unit_price' => '1000']],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.material_id');
    }

    public function test_a_hand_over_with_neither_a_total_nor_lines_is_refused(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/settle", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('total');
    }

    public function test_the_settled_summary_reads_back_what_was_billed(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.settle']));
        $material = Material::factory()->inStock('3')->create(['name' => 'شاشة LCD']);
        $repair = $this->repair();

        $this->postJson("/api/repairs/{$repair->id}/settle", [
            'items' => [
                ['line_type' => 'SERVICE', 'description' => 'أجرة إصلاح', 'quantity' => 1, 'unit_price' => '40000'],
                ['line_type' => 'MATERIAL', 'material_id' => $material->id, 'quantity' => 1, 'unit_price' => '35000'],
            ],
        ])->assertOk();

        $this->getJson("/api/repairs/{$repair->id}/settlement")
            ->assertOk()
            ->assertJsonCount(2, 'data.invoice.items')
            ->assertJsonPath('data.invoice.items.0.description', 'أجرة إصلاح')
            // The part was billed without a description, so it prints its name.
            ->assertJsonPath('data.invoice.items.1.description', 'شاشة LCD')
            ->assertJsonPath('data.series.invoice_prefix', 'RS-')
            ->assertJsonPath('data.series.payment_prefix', 'RCV-');
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function intake(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'device_type' => 'جوال',
            'reported_issue' => 'شاشة مكسورة',
            'estimated_cost' => '80000',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function repair(array $overrides = []): Repair
    {
        return Repair::query()->create(array_merge([
            'customer_id' => $this->customer->id,
            'device_type' => 'جوال',
            'reported_issue' => 'شاشة مكسورة',
            'estimated_cost' => '80000',
            'status' => RepairStatus::RECEIVED,
            'received_at' => now(),
        ], $overrides));
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function userWith(array $permissions): User
    {
        $user = User::factory()->create();

        if ($permissions === []) {
            return $user;
        }

        $role = Role::query()->create([
            'name' => 'دور '.$user->id,
            'slug' => 'role-'.$user->id,
        ]);

        foreach ($permissions as $slug) {
            $role->permissions()->attach(
                Permission::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $slug, 'group' => explode('.', $slug)[0]]
                )
            );
        }

        $user->roles()->attach($role);

        return $user;
    }
}
