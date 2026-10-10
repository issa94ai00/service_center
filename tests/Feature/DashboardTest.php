<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\PaymentType;
use App\Enums\RepairStatus;
use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PurchaseInvoice;
use App\Models\Repair;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\ServiceUnit;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin "today" so period defaults and the sales-today KPI are deterministic.
        Carbon::setTestNow('2026-05-20 10:00:00');

        $this->user = User::factory()->create();

        $role = Role::query()->create(['name' => 'مدير', 'slug' => 'admin']);
        $role->permissions()->attach(Permission::query()->create([
            'name' => 'عرض لوحة التحكم',
            'slug' => 'dashboard.view',
            'group' => 'dashboard',
        ]));
        $this->user->roles()->attach($role);

        $this->customer = Customer::query()->create([
            'name' => 'عميل رئيسي', 'opening_balance' => '0.00', 'is_active' => true,
        ]);

        $this->supplier = Supplier::query()->create([
            'name' => 'مورد رئيسي', 'opening_balance' => '0.00', 'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_defaults_the_period_to_the_current_month(): void
    {
        $period = $this->getJson('/api/dashboard')->assertOk()->json('data.period');

        $this->assertSame('2026-05-01', $period['from']);
        $this->assertSame('2026-05-20', $period['to']);
        $this->assertSame(20, $period['days']);
    }

    public function test_kpis_only_count_posted_documents_inside_the_period(): void
    {
        $this->salesInvoice('2026-05-20', '1000.00');            // today
        $this->salesInvoice('2026-05-02', '500.00');             // in period
        $this->salesInvoice('2026-05-03', '9999.00', DocumentStatus::DRAFT);
        $this->salesInvoice('2026-04-28', '7777.00');            // before period
        $this->purchaseInvoice('2026-05-04', '2000.00');

        $kpis = $this->getJson('/api/dashboard')->assertOk()->json('data.kpis');

        $this->assertSame('1000.00', $kpis['sales_today']['amount']);
        $this->assertSame(1, $kpis['sales_today']['count']);
        $this->assertSame('1500.00', $kpis['sales_period']['amount']);
        $this->assertSame(2, $kpis['sales_period']['count']);
        $this->assertSame('2000.00', $kpis['purchases_period']['amount']);
    }

    public function test_it_reports_receivables_payables_and_the_net_position(): void
    {
        CustomerTransaction::query()->create([
            'customer_id' => $this->customer->id,
            'transaction_date' => '2026-05-02',
            'type' => TransactionType::SALES_INVOICE,
            'debit' => '1000.00', 'credit' => '0.00',
            'created_by' => $this->user->id,
        ]);

        $kpis = $this->getJson('/api/dashboard')->assertOk()->json('data.kpis');

        $this->assertSame('1000.00', $kpis['receivables']);
        $this->assertSame('0.00', $kpis['payables']);
        $this->assertSame('1000.00', $kpis['net_position']);
    }

    public function test_it_totals_posted_payments_by_direction(): void
    {
        $this->payment(PaymentType::CUSTOMER, '2026-05-05', '400.00');
        $this->payment(PaymentType::CUSTOMER, '2026-05-06', '100.00');
        $this->payment(PaymentType::SUPPLIER, '2026-05-07', '250.00');
        $this->payment(PaymentType::CUSTOMER, '2026-05-08', '999.00', DocumentStatus::DRAFT);

        $kpis = $this->getJson('/api/dashboard')->assertOk()->json('data.kpis');

        $this->assertSame('500.00', $kpis['collected_period']);
        $this->assertSame('250.00', $kpis['paid_period']);
    }

    public function test_alerts_count_drafts_and_depleted_stock(): void
    {
        $this->salesInvoice('2026-05-02', '10.00', DocumentStatus::DRAFT);
        $this->salesInvoice('2026-05-03', '10.00', DocumentStatus::DRAFT);
        $this->purchaseInvoice('2026-05-04', '10.00', DocumentStatus::DRAFT);
        $this->payment(PaymentType::CUSTOMER, '2026-05-05', '10.00', DocumentStatus::DRAFT);

        $this->material('كابل', stock: '2.000', min: '10.000');
        $this->material('إسمنت', stock: '0.000', min: '5.000');
        $this->material('بلاط', stock: '900.000', min: '10.000');

        $alerts = $this->getJson('/api/dashboard')->assertOk()->json('data.alerts');

        $this->assertSame(2, $alerts['draft_sales']);
        $this->assertSame(1, $alerts['draft_purchases']);
        $this->assertSame(1, $alerts['draft_payments']);
        $this->assertSame(2, $alerts['low_stock']);
        $this->assertSame(1, $alerts['out_of_stock']);
    }

    public function test_the_trend_zero_fills_days_with_no_documents(): void
    {
        $this->salesInvoice('2026-05-02', '300.00');
        $this->purchaseInvoice('2026-05-04', '700.00');

        $trend = $this->getJson('/api/dashboard?from=2026-05-01&to=2026-05-05')
            ->assertOk()->json('data.trend');

        $this->assertSame('day', $trend['granularity']);
        $this->assertCount(5, $trend['points']);

        $this->assertSame('2026-05-01', $trend['points'][0]['key']);
        $this->assertSame('0.00', $trend['points'][0]['sales']);
        $this->assertSame('300.00', $trend['points'][1]['sales']);
        $this->assertSame('0.00', $trend['points'][2]['sales']);
        $this->assertSame('700.00', $trend['points'][3]['purchases']);
    }

    public function test_a_long_period_switches_the_trend_to_monthly_buckets(): void
    {
        $this->salesInvoice('2026-02-10', '100.00');
        $this->salesInvoice('2026-02-20', '50.00');
        $this->salesInvoice('2026-04-01', '25.00');

        $trend = $this->getJson('/api/dashboard?from=2026-01-01&to=2026-06-30')
            ->assertOk()->json('data.trend');

        $this->assertSame('month', $trend['granularity']);
        $this->assertCount(6, $trend['points']);
        $this->assertSame('2026-01', $trend['points'][0]['key']);
        $this->assertSame('0.00', $trend['points'][0]['sales']);
        // Both February invoices land in one bucket.
        $this->assertSame('150.00', $trend['points'][1]['sales']);
        $this->assertSame('25.00', $trend['points'][3]['sales']);
    }

    public function test_it_ranks_top_customers_by_posted_sales(): void
    {
        $second = Customer::query()->create([
            'name' => 'عميل ثانوي', 'opening_balance' => '0.00', 'is_active' => true,
        ]);

        $this->salesInvoice('2026-05-02', '300.00');
        $this->salesInvoice('2026-05-03', '400.00');
        $this->salesInvoice('2026-05-04', '900.00', customer: $second);

        $rows = $this->getJson('/api/dashboard')->assertOk()->json('data.top_customers');

        $this->assertSame('عميل ثانوي', $rows[0]['name']);
        $this->assertSame('900.00', $rows[0]['total']);
        $this->assertSame('عميل رئيسي', $rows[1]['name']);
        $this->assertSame('700.00', $rows[1]['total']);
        $this->assertSame(2, $rows[1]['invoices']);
    }

    public function test_it_ranks_top_materials_from_posted_invoice_lines(): void
    {
        $cable = $this->material('كابل', stock: '100.000', min: '0.000');
        $tile = $this->material('بلاط', stock: '100.000', min: '0.000');

        $invoice = $this->salesInvoice('2026-05-02', '1000.00');
        $this->line($invoice, $cable, quantity: '20.000', total: '200.00');
        $this->line($invoice, $tile, quantity: '5.000', total: '800.00');

        $draft = $this->salesInvoice('2026-05-03', '5000.00', DocumentStatus::DRAFT);
        $this->line($draft, $cable, quantity: '999.000', total: '5000.00');

        $rows = $this->getJson('/api/dashboard')->assertOk()->json('data.top_materials');

        $this->assertCount(2, $rows);
        $this->assertSame('بلاط', $rows[0]['name']);
        $this->assertSame('800.00', $rows[0]['total']);
        // The draft invoice line must not inflate the cable quantity.
        $this->assertSame('كابل', $rows[1]['name']);
        $this->assertSame('20.000', $rows[1]['quantity']);
    }

    public function test_recent_documents_merge_all_three_ledgers_newest_first(): void
    {
        $this->salesInvoice('2026-05-02', '100.00');
        $this->purchaseInvoice('2026-05-09', '200.00');
        $this->payment(PaymentType::CUSTOMER, '2026-05-15', '50.00');

        $rows = $this->getJson('/api/dashboard')->assertOk()->json('data.recent_documents');

        $this->assertCount(3, $rows);
        $this->assertSame('payment', $rows[0]['kind']);
        $this->assertSame('سند قبض', $rows[0]['kind_label']);
        $this->assertSame('in', $rows[0]['direction']);
        $this->assertSame('purchase_invoice', $rows[1]['kind']);
        $this->assertSame('out', $rows[1]['direction']);
        $this->assertSame('sales_invoice', $rows[2]['kind']);
    }

    public function test_an_empty_system_returns_a_complete_zeroed_payload(): void
    {
        $data = $this->getJson('/api/dashboard')->assertOk()->json('data');

        $this->assertSame('0.00', $data['kpis']['sales_period']['amount']);
        $this->assertSame('0.00', $data['kpis']['net_position']);
        $this->assertSame([], $data['top_customers']);
        $this->assertSame([], $data['recent_documents']);
        // The trend still spans the period so the chart has an axis to draw.
        $this->assertCount(20, $data['trend']['points']);
    }

    public function test_documents_on_the_last_day_of_the_period_are_counted(): void
    {
        $this->salesInvoice('2026-05-31', '600.00');
        $this->purchaseInvoice('2026-05-31', '200.00');
        $this->payment(PaymentType::CUSTOMER, '2026-05-31', '75.00');

        $data = $this->getJson('/api/dashboard?from=2026-05-01&to=2026-05-31')->assertOk()->json('data');

        $this->assertSame('600.00', $data['kpis']['sales_period']['amount']);
        $this->assertSame('200.00', $data['kpis']['purchases_period']['amount']);
        $this->assertSame('75.00', $data['kpis']['collected_period']);
        $this->assertSame('600.00', $data['top_customers'][0]['total']);
        $this->assertSame('600.00', end($data['trend']['points'])['sales']);
    }

    public function test_it_omits_the_comparison_unless_it_is_asked_for(): void
    {
        $data = $this->getJson('/api/dashboard')->assertOk()->json('data');

        $this->assertNull($data['comparison']);
    }

    public function test_compare_returns_the_preceding_window_of_the_same_length(): void
    {
        // The period on screen is 2026-05-10..2026-05-12, so the one behind it
        // is the three days ending the day before it starts.
        $comparison = $this->getJson('/api/dashboard?from=2026-05-10&to=2026-05-12&compare=1')
            ->assertOk()->json('data.comparison');

        $this->assertSame('2026-05-07', $comparison['from']);
        $this->assertSame('2026-05-09', $comparison['to']);
        $this->assertSame(3, $comparison['days']);
    }

    public function test_compare_shifts_the_default_month_period_back_a_full_month(): void
    {
        $comparison = $this->getJson('/api/dashboard?compare=1')->assertOk()->json('data.comparison');

        // 2026-05-01..2026-05-20 is 20 days; the 20 days before it end 2026-04-30.
        $this->assertSame('2026-04-11', $comparison['from']);
        $this->assertSame('2026-04-30', $comparison['to']);
    }

    public function test_compare_totals_only_documents_inside_the_previous_window(): void
    {
        $this->salesInvoice('2026-05-11', '1000.00');            // current period
        $this->salesInvoice('2026-05-08', '400.00');             // previous period
        $this->salesInvoice('2026-05-07', '100.00');             // previous period, first day
        $this->salesInvoice('2026-05-06', '9999.00');            // before both
        $this->salesInvoice('2026-05-08', '5555.00', DocumentStatus::DRAFT);
        $this->purchaseInvoice('2026-05-08', '300.00');
        $this->payment(PaymentType::CUSTOMER, '2026-05-09', '70.00');
        $this->payment(PaymentType::SUPPLIER, '2026-05-09', '30.00');

        $data = $this->getJson('/api/dashboard?from=2026-05-10&to=2026-05-12&compare=1')
            ->assertOk()->json('data');

        $this->assertSame('1000.00', $data['kpis']['sales_period']['amount']);

        $previous = $data['comparison']['kpis'];
        $this->assertSame('500.00', $previous['sales_period']['amount']);
        $this->assertSame(2, $previous['sales_period']['count']);
        $this->assertSame('300.00', $previous['purchases_period']['amount']);
        $this->assertSame('70.00', $previous['collected_period']);
        $this->assertSame('30.00', $previous['paid_period']);
    }

    public function test_the_comparison_carries_only_window_figures(): void
    {
        $previous = $this->getJson('/api/dashboard?compare=1')->assertOk()->json('data.comparison.kpis');

        // Balances and shortlists describe now, not a window — recomputing them
        // for the previous period would double the dashboard's cost for nothing.
        $this->assertSame(
            ['sales_period', 'purchases_period', 'collected_period', 'paid_period', 'expenses_period'],
            array_keys($previous),
        );
    }

    public function test_it_rejects_a_non_boolean_compare_flag(): void
    {
        $this->getJson('/api/dashboard?compare=maybe')
            ->assertStatus(422)
            ->assertJsonValidationErrors('compare');
    }

    public function test_it_rejects_a_reversed_period(): void
    {
        $this->getJson('/api/dashboard?from=2026-05-20&to=2026-05-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }

    public function test_it_reports_workshop_metrics_and_alerts(): void
    {
        Repair::query()->create([
            'customer_id' => $this->customer->id,
            'device_type' => 'هاتف',
            'brand' => 'Apple',
            'model' => 'iPhone 13',
            'reported_issue' => 'كسر شاشة',
            'status' => RepairStatus::READY,
            'received_at' => '2026-05-02 10:00:00',
            'final_cost' => '150.00',
        ]);

        Repair::query()->create([
            'customer_id' => $this->customer->id,
            'device_type' => 'هاتف',
            'brand' => 'Samsung',
            'model' => 'S22',
            'reported_issue' => 'بطارية ضعيفة',
            'status' => RepairStatus::WAITING_APPROVAL,
            'received_at' => '2026-05-05 11:00:00',
            'estimated_cost' => '200.00',
        ]);

        Repair::query()->create([
            'customer_id' => $this->customer->id,
            'device_type' => 'حاسوب',
            'brand' => 'Apple',
            'model' => 'MacBook Pro',
            'reported_issue' => 'صيانة لوحة أم',
            'status' => RepairStatus::DELIVERED,
            'received_at' => '2026-05-01 09:00:00',
            'delivered_at' => '2026-05-10 14:00:00',
            'final_cost' => '500.00',
            'settled_at' => '2026-05-10 14:00:00',
        ]);

        $this->grant('repairs.view');
        $data = $this->getJson('/api/dashboard')->assertOk()->json('data');

        $this->assertSame(1, $data['alerts']['repairs_ready']);
        $this->assertSame(1, $data['alerts']['repairs_waiting_approval']);

        $workshop = $data['workshop'];
        $this->assertSame(3, $workshop['received_count']);
        $this->assertSame(1, $workshop['delivered_count']);
        $this->assertSame('500.00', $workshop['delivered_revenue']);
        $this->assertSame(2, $workshop['active_in_shop']);
        $this->assertSame(1, $workshop['ready_for_pickup']);

        $this->assertSame('Apple', $data['top_repair_brands'][0]['name']);
        $this->assertSame(2, $data['top_repair_brands'][0]['count']);
    }

    public function test_it_reports_profit_overview_for_the_period(): void
    {
        $cable = $this->material('كابل أصلي', stock: '50.000', min: '5.000');
        $invoice = $this->salesInvoice('2026-05-05', '1000.00');
        $this->line($invoice, $cable, quantity: '10.000', total: '1000.00');

        $cat = ExpenseCategory::query()->create(['name' => 'إيجار']);
        Expense::query()->create([
            'expense_category_id' => $cat->id,
            'amount' => '150.00',
            'expense_date' => '2026-05-06',
            'payment_method' => 'CASH',
            'recorded_by' => $this->user->id,
            'is_counted' => true,
        ]);

        $profit = $this->getJson('/api/dashboard')->assertOk()->json('data.profit_overview');

        $this->assertSame('1000.00', $profit['revenue']);
        $this->assertSame('10.00', $profit['cogs']);
        $this->assertSame('990.00', $profit['gross_profit']);
        $this->assertSame(99.0, (float) $profit['gross_margin']);
        $this->assertSame('150.00', $profit['operating_expenses']);
        $this->assertSame('840.00', $profit['net_profit']);
        $this->assertFalse($profit['is_loss']);
    }

    public function test_repair_sections_are_withheld_without_repairs_view(): void
    {
        $this->repair(RepairStatus::READY);

        $data = $this->getJson('/api/dashboard')->assertOk()->json('data');

        $this->assertFalse($data['sections']['repairs']);
        $this->assertNull($data['workshop']);
        $this->assertNull($data['top_repair_brands']);
        $this->assertArrayNotHasKey('repairs_ready', $data['alerts']);
    }

    public function test_repair_sections_are_withheld_while_the_repairs_feature_is_off(): void
    {
        $this->repair(RepairStatus::READY);
        $this->grant('repairs.view');
        Setting::query()->updateOrCreate(['key' => 'feature_repairs'], ['value' => '0', 'group' => 'features', 'type' => 'bool']);

        $data = $this->getJson('/api/dashboard')->assertOk()->json('data');

        $this->assertFalse($data['sections']['repairs']);
        $this->assertNull($data['workshop']);
    }

    public function test_switched_off_repairs_leave_the_dashboard_even_for_master(): void
    {
        Setting::query()->updateOrCreate(['key' => 'feature_repairs'], ['value' => '0', 'group' => 'features', 'type' => 'bool']);
        $master = User::factory()->create();
        $master->roles()->attach(Role::query()->create(['name' => 'المدير العام', 'slug' => 'master']));

        $data = $this->actingAs($master)->getJson('/api/dashboard')->assertOk()->json('data');

        // Technicians are paid from repairs, so they go with them.
        $this->assertSame(['repairs' => false, 'technicians' => false, 'services' => true], $data['sections']);
        $this->assertNull($data['workshop']);
        $this->assertNull($data['top_technicians']);
    }

    public function test_switched_off_services_leave_the_dashboard_even_for_master(): void
    {
        Setting::query()->updateOrCreate(['key' => 'feature_services'], ['value' => '0', 'group' => 'features', 'type' => 'bool']);
        $master = User::factory()->create();
        $master->roles()->attach(Role::query()->create(['name' => 'المدير العام', 'slug' => 'master']));

        $data = $this->actingAs($master)->getJson('/api/dashboard')->assertOk()->json('data');

        $this->assertFalse($data['sections']['services']);
        $this->assertNull($data['top_services']);
    }

    public function test_master_sees_every_section_while_features_are_on(): void
    {
        $master = User::factory()->create();
        $master->roles()->attach(Role::query()->create(['name' => 'المدير العام', 'slug' => 'master']));

        $data = $this->actingAs($master)->getJson('/api/dashboard')->assertOk()->json('data');

        $this->assertSame(['repairs' => true, 'technicians' => true, 'services' => true], $data['sections']);
    }

    public function test_top_technicians_need_technicians_view(): void
    {
        $technician = Technician::query()->create(['name' => 'فني الشاشات', 'commission_rate' => '10.00', 'opening_balance' => '0.00', 'is_active' => true]);
        $this->repair(RepairStatus::DELIVERED, ['technician_id' => $technician->id, 'delivered_at' => '2026-05-10 14:00:00', 'settled_at' => '2026-05-10 14:00:00', 'final_cost' => '300.00']);

        $this->assertNull($this->getJson('/api/dashboard')->assertOk()->json('data.top_technicians'));

        $this->grant('technicians.view');
        $technicians = $this->getJson('/api/dashboard')->assertOk()->json('data.top_technicians');

        $this->assertSame('فني الشاشات', $technicians['rows'][0]['name']);
        $this->assertSame(1, $technicians['rows'][0]['repairs_count']);
        $this->assertArrayHasKey('balance_owed', $technicians);
    }

    public function test_top_services_need_services_view(): void
    {
        $invoice = $this->salesInvoice('2026-05-02', '250.00');
        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'line_type' => 'SERVICE',
            'description' => 'تبديل شاشة',
            'quantity' => '1.000',
            'unit_price' => '250.00',
            'line_total' => '250.00',
        ]);

        $this->assertNull($this->getJson('/api/dashboard')->assertOk()->json('data.top_services'));

        $this->grant('services.view');
        $services = $this->getJson('/api/dashboard')->assertOk()->json('data.top_services');

        $this->assertSame('تبديل شاشة', $services[0]['name']);
        $this->assertSame('250.00', $services[0]['revenue']);
    }

    public function test_it_requires_the_dashboard_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/dashboard')->assertForbidden();
    }

    // ------------------------------------------------------------- factories

    protected function salesInvoice(string $date, string $total, DocumentStatus $status = DocumentStatus::POSTED, ?Customer $customer = null): SalesInvoice
    {
        return SalesInvoice::query()->create([
            'invoice_no' => 'SI-'.str_pad((string) (SalesInvoice::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'customer_id' => ($customer ?? $this->customer)->id,
            'invoice_date' => $date,
            'status' => $status,
            'subtotal' => $total, 'total' => $total, 'remaining' => $total,
            'created_by' => $this->user->id,
        ]);
    }

    protected function purchaseInvoice(string $date, string $total, DocumentStatus $status = DocumentStatus::POSTED): PurchaseInvoice
    {
        return PurchaseInvoice::query()->create([
            'invoice_no' => 'PI-'.str_pad((string) (PurchaseInvoice::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'supplier_id' => $this->supplier->id,
            'purchase_date' => $date,
            'status' => $status,
            'subtotal' => $total, 'total' => $total, 'remaining' => $total,
            'created_by' => $this->user->id,
        ]);
    }

    protected function payment(PaymentType $type, string $date, string $amount, DocumentStatus $status = DocumentStatus::POSTED): Payment
    {
        return Payment::query()->create([
            'payment_type' => $type,
            'customer_id' => $type === PaymentType::CUSTOMER ? $this->customer->id : null,
            'supplier_id' => $type === PaymentType::SUPPLIER ? $this->supplier->id : null,
            'amount' => $amount,
            'payment_method' => 'CASH',
            'payment_date' => $date,
            'status' => $status,
            'recorded_by' => $this->user->id,
        ]);
    }

    protected function material(string $name, string $stock, string $min): Material
    {
        return Material::factory()->create([
            'name' => $name,
            'sku' => strtoupper(substr(md5($name), 0, 8)),
            'cost_price' => '1.00', 'sale_price' => '2.00',
            'min_stock' => $min, 'stock_quantity' => $stock,
        ]);
    }

    /** Adds $slug to the test user's role. */
    protected function grant(string $slug): void
    {
        $this->user->roles->first()->permissions()->attach(Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'group' => explode('.', $slug)[0]],
        ));
        $this->user->load('roles');
    }

    protected function repair(RepairStatus $status, array $attributes = []): Repair
    {
        return Repair::query()->create($attributes + [
            'customer_id' => $this->customer->id,
            'device_type' => 'هاتف',
            'brand' => 'Apple',
            'model' => 'iPhone 13',
            'reported_issue' => 'كسر شاشة',
            'status' => $status,
            'received_at' => '2026-05-02 10:00:00',
        ]);
    }

    protected function line(SalesInvoice $invoice, Material $material, string $quantity, string $total): void
    {
        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'line_type' => 'MATERIAL',
            'material_id' => $material->id,
            'quantity' => $quantity,
            'unit_price' => '10.00',
            'line_total' => $total,
        ]);
    }
}
