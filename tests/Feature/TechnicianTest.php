<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Repair;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\Setting;
use App\Models\Technician;
use App\Models\TechnicianTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * خبير الصيانة من طرفيه: إسناده إلى بلاغ يقيّد عمولته عند التسوية، وصرف
 * مستحقاته يُنقص رصيده — وكلاهما يظهر في التقرير وكشف الحساب.
 */
class TechnicianTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-20 10:00:00');

        Setting::query()->insert([
            ['key' => 'repair_receipt_prefix', 'value' => 'RPR-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'currency_symbol', 'value' => 'ل.س', 'group' => 'general', 'type' => 'string'],
            ['key' => 'invoice_prefix', 'value' => 'SL-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'payment_prefix', 'value' => 'PAY-', 'group' => 'numbers', 'type' => 'string'],
        ]);

        $this->customer = Customer::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ------------------------------------------------------------------- CRUD

    public function test_it_creates_a_technician_with_a_commission_rate(): void
    {
        $this->actingAs($this->userWith(['technicians.create']));

        $this->postJson('/api/technicians', [
            'name' => 'سامر',
            'specialty' => 'بوردات',
            'commission_rate' => '30',
        ])->assertCreated()->assertJsonPath('data.commission_rate', '30.00');

        $this->assertDatabaseHas('technicians', ['name' => 'سامر', 'commission_rate' => '30.00']);
    }

    public function test_it_rejects_a_commission_rate_outside_zero_to_a_hundred(): void
    {
        $this->actingAs($this->userWith(['technicians.create']));

        $this->postJson('/api/technicians', ['name' => 'سامر', 'commission_rate' => '140'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('commission_rate');
    }

    public function test_listing_technicians_requires_the_permission(): void
    {
        $this->actingAs($this->userWith([]));

        $this->getJson('/api/technicians')->assertForbidden();
    }

    public function test_a_technician_with_financial_history_cannot_be_deleted(): void
    {
        $technician = Technician::factory()->create();
        $user = $this->userWith(['technicians.delete']);
        $this->actingAs($user);

        TechnicianTransaction::query()->create([
            'technician_id' => $technician->id,
            'transaction_date' => '2026-05-02',
            'type' => TransactionType::REPAIR_COMMISSION,
            'debit' => '0.00', 'credit' => '100.00',
            'created_by' => $user->id,
        ]);

        $this->deleteJson("/api/technicians/{$technician->id}")->assertStatus(422);
        $this->assertDatabaseHas('technicians', ['id' => $technician->id, 'soft_deleted_at' => null]);
    }

    public function test_a_technician_with_no_history_can_be_deleted(): void
    {
        $technician = Technician::factory()->create();
        $this->actingAs($this->userWith(['technicians.delete']));

        $this->deleteJson("/api/technicians/{$technician->id}")->assertOk();
        $this->assertSoftDeleted('technicians', ['id' => $technician->id]);
    }

    public function test_options_only_offers_active_technicians(): void
    {
        Technician::factory()->create(['name' => 'فعّال']);
        Technician::factory()->inactive()->create(['name' => 'معطّل']);
        $this->actingAs($this->userWith(['technicians.view']));

        $names = collect($this->getJson('/api/technicians/options')->assertOk()->json('data'))
            ->pluck('name');

        $this->assertTrue($names->contains('فعّال'));
        $this->assertFalse($names->contains('معطّل'));
    }

    // ------------------------------------------------------------- commission

    public function test_settling_a_repair_credits_the_technician_his_rate_of_the_final_cost(): void
    {
        $technician = Technician::factory()->rate('25.00')->create();
        $repair = $this->openRepair($technician);

        $this->actingAs($this->userWith(['repairs.settle']));
        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])->assertOk();

        $this->assertDatabaseHas('technician_transactions', [
            'technician_id' => $technician->id,
            'type' => TransactionType::REPAIR_COMMISSION->value,
            'reference_type' => Repair::class,
            'reference_id' => $repair->id,
            'debit' => '0.00',
            'credit' => '20000.00',
        ]);

        // النسبة والمبلغ يُلتقطان على البلاغ نفسه.
        $repair->refresh();
        $this->assertSame('25.00', $repair->commission_rate);
        $this->assertSame('20000.00', $repair->commission_amount);
    }

    public function test_the_settlement_may_override_the_rate_for_one_repair(): void
    {
        $technician = Technician::factory()->rate('25.00')->create();
        $repair = $this->openRepair($technician);

        $this->actingAs($this->userWith(['repairs.settle']));
        $this->postJson("/api/repairs/{$repair->id}/settle", [
            'total' => '80000',
            'commission_rate' => '10',
        ])->assertOk();

        $repair->refresh();
        $this->assertSame('10.00', $repair->commission_rate);
        $this->assertSame('8000.00', $repair->commission_amount);
        // نسبة الخبير المحفوظة لم تتغيّر بتجاوزٍ لبلاغ واحد.
        $this->assertSame('25.00', $technician->refresh()->commission_rate);
    }

    public function test_raising_a_technicians_rate_does_not_rewrite_a_closed_repair(): void
    {
        $technician = Technician::factory()->rate('25.00')->create();
        $repair = $this->openRepair($technician);

        $this->actingAs($this->userWith(['repairs.settle', 'technicians.edit']));
        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])->assertOk();

        $this->putJson("/api/technicians/{$technician->id}", ['commission_rate' => '50'])->assertOk();

        $repair->refresh();
        $this->assertSame('25.00', $repair->commission_rate);
        $this->assertSame('20000.00', $repair->commission_amount);
        $this->assertSame(20000.0, (float) TechnicianTransaction::query()
            ->where('reference_id', $repair->id)->sum('credit'));
    }

    public function test_a_repair_with_no_technician_writes_nothing_to_any_ledger(): void
    {
        $repair = $this->openRepair(null);

        $this->actingAs($this->userWith(['repairs.settle']));
        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])->assertOk();

        $this->assertDatabaseCount('technician_transactions', 0);
    }

    public function test_a_free_repair_leaves_no_commission_entry(): void
    {
        $technician = Technician::factory()->rate('25.00')->create();
        $repair = $this->openRepair($technician);

        $this->actingAs($this->userWith(['repairs.settle']));
        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '0'])->assertOk();

        $this->assertDatabaseCount('technician_transactions', 0);
        $this->assertSame('0.00', $repair->refresh()->commission_amount);
    }

    public function test_re_settling_after_a_cancelled_invoice_reverses_the_first_commission(): void
    {
        $technician = Technician::factory()->rate('25.00')->create();
        $repair = $this->openRepair($technician);

        $this->actingAs($this->userWith(['repairs.settle', 'sales.cancel']));
        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])->assertOk();

        // إلغاء فاتورة البلاغ يعيد فتحه للتسوية.
        $invoice = SalesInvoice::query()->firstOrFail();
        $this->postJson("/api/sales/{$invoice->id}/cancel", ['reason' => 'خطأ في السعر'])->assertOk();

        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '40000'])->assertOk();

        // القيد لا يُعدَّل: عمولة ثم عكسها ثم العمولة الجديدة.
        $ledger = TechnicianTransaction::query()->where('technician_id', $technician->id)->orderBy('id')->get();
        $this->assertCount(3, $ledger);
        $this->assertSame('20000.00', $ledger[0]->credit);
        $this->assertSame(TransactionType::REVERSAL, $ledger[1]->type);
        $this->assertSame('20000.00', $ledger[1]->debit);
        $this->assertSame('10000.00', $ledger[2]->credit);

        // والصافي هو العمولة الجديدة وحدها.
        $net = $ledger->sum(fn ($row) => (float) $row->credit - (float) $row->debit);
        $this->assertSame(10000.0, $net);
    }

    // --------------------------------------------------------------- payouts

    public function test_a_technician_payout_debits_his_ledger(): void
    {
        $technician = Technician::factory()->create();
        $this->actingAs($this->userWith(['payments.create', 'payments.confirm']));

        $payment = $this->postJson('/api/payments', [
            'payment_type' => 'TECHNICIAN',
            'technician_id' => $technician->id,
            'amount' => '15000',
            'payment_date' => '2026-05-10',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/payments/{$payment}/post")->assertOk();

        $this->assertDatabaseHas('technician_transactions', [
            'technician_id' => $technician->id,
            'type' => TransactionType::TECHNICIAN_PAYMENT->value,
            'reference_type' => Payment::class,
            'reference_id' => $payment,
            'debit' => '15000.00',
            'credit' => '0.00',
        ]);
    }

    public function test_a_technician_payout_may_not_be_allocated_to_an_invoice(): void
    {
        $technician = Technician::factory()->create();
        $this->actingAs($this->userWith(['payments.create']));

        $this->postJson('/api/payments', [
            'payment_type' => 'TECHNICIAN',
            'technician_id' => $technician->id,
            'amount' => '15000',
            'allocations' => [['invoice_type' => 'SALES', 'sales_invoice_id' => 1, 'amount' => '15000']],
        ])->assertStatus(422)->assertJsonValidationErrors('allocations');
    }

    public function test_a_technician_payout_needs_a_technician(): void
    {
        $this->actingAs($this->userWith(['payments.create']));

        $this->postJson('/api/payments', ['payment_type' => 'TECHNICIAN', 'amount' => '15000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('technician_id');
    }

    public function test_cancelling_a_payout_credits_the_ledger_back(): void
    {
        $technician = Technician::factory()->create();
        $this->actingAs($this->userWith(['payments.create', 'payments.confirm', 'payments.cancel']));

        $payment = $this->postJson('/api/payments', [
            'payment_type' => 'TECHNICIAN',
            'technician_id' => $technician->id,
            'amount' => '15000',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/payments/{$payment}/post")->assertOk();
        $this->postJson("/api/payments/{$payment}/cancel", ['reason' => 'صُرف مرتين'])->assertOk();

        $net = TechnicianTransaction::query()->where('technician_id', $technician->id)
            ->get()->sum(fn ($row) => (float) $row->debit - (float) $row->credit);

        $this->assertSame(0.0, $net);
    }

    // ---------------------------------------------------------------- reports

    public function test_the_summary_report_nets_commission_against_payouts(): void
    {
        $technician = Technician::factory()->rate('25.00')->create(['name' => 'سامر']);
        $repair = $this->openRepair($technician);

        $this->actingAs($this->userWith(['repairs.settle', 'payments.create', 'payments.confirm', 'reports.view']));
        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])->assertOk();

        $payment = $this->postJson('/api/payments', [
            'payment_type' => 'TECHNICIAN',
            'technician_id' => $technician->id,
            'amount' => '5000',
            'payment_date' => '2026-05-20',
        ])->json('data.id');
        $this->postJson("/api/payments/{$payment}/post")->assertOk();

        $row = $this->getJson('/api/reports/technician-summary?from=2026-05-01&to=2026-05-31')
            ->assertOk()->json('data.rows.0');

        $this->assertSame('سامر', $row['name']);
        $this->assertSame(1, $row['repairs_count']);
        $this->assertSame('80000.00', $row['revenue']);
        $this->assertSame('20000.00', $row['commission']);
        $this->assertSame('5000.00', $row['paid']);
        // الرصيد بإشارة الخبير: ما زال له ١٥٬٠٠٠.
        $this->assertSame('15000.00', $row['balance']);
    }

    public function test_the_summary_report_excludes_repairs_settled_outside_the_period(): void
    {
        $technician = Technician::factory()->rate('25.00')->create();
        $repair = $this->openRepair($technician);

        $this->actingAs($this->userWith(['repairs.settle', 'reports.view']));
        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])->assertOk();

        $row = $this->getJson('/api/reports/technician-summary?from=2026-06-01&to=2026-06-30')
            ->assertOk()->json('data.rows.0');

        $this->assertSame(0, $row['repairs_count']);
        $this->assertSame('0.00', $row['commission']);
        // الرصيد قائم لا حركة فترة، فهو صفر حتى نهاية حزيران؟ لا:
        // القيد كُتب في أيار، وهو قبل نهاية الفترة، فيظهر في الرصيد.
        $this->assertSame('20000.00', $row['balance']);
    }

    public function test_the_detail_report_lists_one_row_per_settled_repair(): void
    {
        $technician = Technician::factory()->rate('25.00')->create();
        $first = $this->openRepair($technician);
        $second = $this->openRepair($technician);

        $this->actingAs($this->userWith(['repairs.settle', 'reports.view']));
        $this->postJson("/api/repairs/{$first->id}/settle", ['total' => '80000'])->assertOk();
        $this->postJson("/api/repairs/{$second->id}/settle", ['total' => '40000'])->assertOk();

        $body = $this->getJson("/api/reports/technician-repairs?technician_id={$technician->id}")
            ->assertOk()->json('data');

        $this->assertCount(2, $body['rows']);
        $this->assertSame('2', $body['totals']['repairs_count']);
        $this->assertSame('120000.00', $body['totals']['revenue']);
        $this->assertSame('30000.00', $body['totals']['commission']);
        $this->assertSame('25.00', $body['rows'][0]['commission_rate']);
    }

    public function test_the_detail_report_ignores_repairs_that_are_not_settled(): void
    {
        $technician = Technician::factory()->create();
        $this->openRepair($technician);

        $this->actingAs($this->userWith(['reports.view']));

        $this->getJson('/api/reports/technician-repairs')
            ->assertOk()
            ->assertJsonPath('data.totals.repairs_count', '0');
    }

    public function test_the_reports_require_the_reports_permission(): void
    {
        $this->actingAs($this->userWith([]));

        $this->getJson('/api/reports/technician-summary')->assertForbidden();
        $this->getJson('/api/reports/technician-repairs')->assertForbidden();
    }

    // ------------------------------------------------------------- statement

    public function test_the_statement_runs_a_balance_over_commission_and_payouts(): void
    {
        $technician = Technician::factory()->rate('25.00')->create();
        $repair = $this->openRepair($technician);

        $this->actingAs($this->userWith([
            'repairs.settle', 'payments.create', 'payments.confirm', 'statements.view',
        ]));

        $this->postJson("/api/repairs/{$repair->id}/settle", ['total' => '80000'])->assertOk();

        $payment = $this->postJson('/api/payments', [
            'payment_type' => 'TECHNICIAN',
            'technician_id' => $technician->id,
            'amount' => '5000',
            'payment_date' => '2026-05-21',
        ])->json('data.id');
        $this->postJson("/api/payments/{$payment}/post")->assertOk();

        $statement = $this->getJson("/api/technicians/{$technician->id}/statement")
            ->assertOk()->json('data');

        $this->assertCount(2, $statement['rows']);

        // العمولة دائنة: الرصيد يمضي إلى سالب ٢٠٬٠٠٠ باصطلاح الدفتر.
        $this->assertSame('عمولة صيانة', $statement['rows'][0]['type_label']);
        $this->assertSame('20000.00', $statement['rows'][0]['credit']);
        $this->assertSame('-20000.00', $statement['rows'][0]['running_balance']);
        // رقم الإيصال يُشتقّ من معرّف البلاغ، لا من عمود محفوظ.
        $this->assertSame('RPR-'.$repair->id, $statement['rows'][0]['reference_no']);
        $this->assertSame('repair', $statement['rows'][0]['reference']['kind']);

        $this->assertSame('سند صرف لخبير', $statement['rows'][1]['type_label']);
        $this->assertSame('5000.00', $statement['rows'][1]['debit']);
        $this->assertSame('-15000.00', $statement['rows'][1]['running_balance']);

        $this->assertSame('-15000.00', $statement['closing_balance']);
    }

    public function test_the_statement_requires_the_statements_permission(): void
    {
        $technician = Technician::factory()->create();
        $this->actingAs($this->userWith([]));

        $this->getJson("/api/technicians/{$technician->id}/statement")->assertForbidden();
    }

    // ------------------------------------------------------------- factories

    /**
     * بلاغ مفتوح مُسنَد إلى خبير، جاهز للتسوية.
     */
    protected function openRepair(?Technician $technician): Repair
    {
        return Repair::query()->create([
            'customer_id' => $this->customer->id,
            'technician_id' => $technician?->id,
            'device_type' => 'جوال',
            'reported_issue' => 'شاشة مكسورة',
            'estimated_cost' => '80000.00',
            'status' => 'UNDER_REPAIR',
            'received_at' => '2026-05-02 09:00:00',
        ]);
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
