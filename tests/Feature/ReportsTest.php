<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Permission;
use App\Models\Repair;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\ServiceUnit;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\Technician;
use App\Models\TechnicianTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $role = Role::query()->create(['name' => 'محاسب', 'slug' => 'accountant']);
        $role->permissions()->attach(Permission::query()->create([
            'name' => 'عرض التقارير',
            'slug' => 'reports.view',
            'group' => 'reports',
        ]));
        $this->user->roles()->attach($role);

        $this->actingAs($this->user);
    }

    // ------------------------------------------------------------- low stock

    public function test_low_stock_lists_materials_at_or_below_their_minimum(): void
    {
        $this->material('كابل كهربائي', stock: '5.000', min: '20.000', sale: '10.5');
        $this->material('إسمنت', stock: '0.000', min: '10.000');
        $this->material('بلاط', stock: '500.000', min: '50.000');

        $data = $this->getJson('/api/reports/low-stock')->assertOk()->json('data');

        $this->assertCount(2, $data);
        // Scarcest first: cement is 10 short, cable is 15 short.
        $this->assertSame('كابل كهربائي', $data[0]['name']);
        $this->assertSame('15.000', $data[0]['shortfall']);
        $this->assertSame('10.50', $data[0]['sale_price']);
        $this->assertTrue($data[0]['is_low']);
        $this->assertFalse($data[0]['is_out_of_stock']);
        $this->assertTrue($data[1]['is_out_of_stock']);
    }

    public function test_low_stock_can_be_searched(): void
    {
        $this->material('كابل كهربائي', stock: '5.000', min: '20.000', sku: 'CBL-1');
        $this->material('إسمنت', stock: '0.000', min: '10.000', sku: 'CEM-1');

        $data = $this->getJson('/api/reports/low-stock?search=CBL')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('كابل كهربائي', $data[0]['name']);
    }

    // --------------------------------------------------------------- summary

    public function test_sales_summary_aggregates_totals_and_days(): void
    {
        $customer = $this->customer('عميل أ');

        $this->salesInvoice($customer, '2026-05-01', total: '1000.00', paid: '400.00', remaining: '600.00');
        $this->salesInvoice($customer, '2026-05-01', total: '500.50', paid: '500.50', remaining: '0.00');
        $this->salesInvoice($customer, '2026-05-03', total: '250.25', paid: '0.00', remaining: '250.25');
        // Draft and out-of-range invoices must not count.
        $this->salesInvoice($customer, '2026-05-02', total: '9999.00', status: DocumentStatus::DRAFT);
        $this->salesInvoice($customer, '2026-06-10', total: '7777.00');

        $data = $this->getJson('/api/reports/sales-summary?from=2026-05-01&to=2026-05-31')
            ->assertOk()->json('data');

        $this->assertSame(3, $data['count']);
        $this->assertSame('1750.75', $data['total']);
        $this->assertSame('900.50', $data['paid']);
        $this->assertSame('850.25', $data['outstanding']);

        $this->assertCount(2, $data['by_day']);
        $this->assertSame('2026-05-01', $data['by_day'][0]['date']);
        $this->assertSame(2, $data['by_day'][0]['count']);
        $this->assertSame('1500.50', $data['by_day'][0]['total']);
        $this->assertSame('2026-05-03', $data['by_day'][1]['date']);
    }

    public function test_a_document_on_the_last_day_of_the_range_is_included(): void
    {
        $customer = $this->customer('عميل حدّي');
        $this->salesInvoice($customer, '2026-05-31', total: '400.00', remaining: '400.00');

        $data = $this->getJson('/api/reports/sales-summary?from=2026-05-01&to=2026-05-31')
            ->assertOk()->json('data');

        $this->assertSame(1, $data['count']);
        $this->assertSame('400.00', $data['total']);
        $this->assertCount(1, $data['by_day']);
    }

    public function test_receivables_as_of_includes_the_boundary_day(): void
    {
        $customer = $this->customer('عميل', opening: '0.00');
        $this->customerTransaction($customer, '2026-02-28', debit: '300.00');

        $data = $this->getJson('/api/reports/receivables?as_of=2026-02-28')->assertOk()->json('data');

        $this->assertSame('300.00', $data['totals']['balance']);
    }

    public function test_stock_movements_include_the_last_day_of_the_range(): void
    {
        $material = $this->material('مادة', stock: '0.000', min: '0.000');
        $this->movement($material, '2026-03-31', 'PURCHASE', 'IN', '7.000');

        $rows = $this->getJson('/api/reports/stock-movements?from=2026-03-01&to=2026-03-31')
            ->assertOk()->json('data.rows');

        $this->assertCount(1, $rows);
    }

    public function test_summary_requires_a_period(): void
    {
        $this->getJson('/api/reports/sales-summary')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from', 'to']);

        $this->getJson('/api/reports/profit-summary')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from', 'to']);
    }

    public function test_profit_summary_aggregates_revenue_cogs_expenses_and_profits(): void
    {
        $material = Material::factory()->create([
            'name' => 'شاشة آيفون',
            'sku' => 'IPH-SCR',
            'cost_price' => '30.00',
            'sale_price' => '50.00',
            'stock_quantity' => '100.000',
            'min_stock' => '5.000',
        ]);

        $customer = $this->customer('عميل تجربة');

        // Invoice 1: Material sold
        $invoice1 = $this->salesInvoice($customer, '2026-05-10', total: '100.00', paid: '100.00');
        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice1->id,
            'line_type' => 'MATERIAL',
            'material_id' => $material->id,
            'quantity' => '2.000',
            'unit_price' => '50.00',
            'line_total' => '100.00',
        ]);

        // Invoice 2: Service sold (no material cost)
        $invoice2 = $this->salesInvoice($customer, '2026-05-12', total: '40.00', paid: '40.00');
        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice2->id,
            'line_type' => 'SERVICE',
            'description' => 'صيانة شاشة وبرمجة',
            'quantity' => '1.000',
            'unit_price' => '40.00',
            'line_total' => '40.00',
        ]);

        // Operating expense
        $category = ExpenseCategory::query()->create(['name' => 'كهرباء ومياه']);
        Expense::query()->create([
            'expense_no' => 'EXP-0001',
            'expense_category_id' => $category->id,
            'amount' => '15.00',
            'payment_method' => 'CASH',
            'expense_date' => '2026-05-11',
            'payee' => 'شركة الكهرباء',
            'status' => 'POSTED',
            'recorded_by' => $this->user->id,
        ]);

        // Technician commission
        $technician = Technician::query()->create([
            'name' => 'خبير الصيانة أحمد',
            'commission_rate' => '20.00',
        ]);
        TechnicianTransaction::query()->create([
            'technician_id' => $technician->id,
            'transaction_date' => '2026-05-12',
            'type' => TransactionType::REPAIR_COMMISSION,
            'reference_type' => Repair::class,
            'reference_id' => 1,
            'debit' => '0.00',
            'credit' => '8.00',
            'description' => 'عمولة صيانة',
            'created_by' => $this->user->id,
        ]);

        $res = $this->getJson('/api/reports/profit-summary?from=2026-05-01&to=2026-05-31')
            ->assertOk()
            ->json('data');

        // Revenue: 100 + 40 = 140.00
        $this->assertSame('140.00', $res['revenue']['total']);
        $this->assertSame('100.00', $res['revenue']['materials_revenue']);
        $this->assertSame('40.00', $res['revenue']['services_revenue']);
        $this->assertSame(2, $res['revenue']['invoices_count']);

        // COGS: 2 items * 30.00 = 60.00
        $this->assertSame('60.00', $res['cogs']['total']);

        // Gross profit: 140.00 - 60.00 = 80.00 (margin: 80 / 140 = 57.14%)
        $this->assertSame('80.00', $res['gross_profit']['total']);
        $this->assertEquals(57.14, $res['gross_profit']['margin_percent']);

        // Operating costs: 15.00 expense + 8.00 commission = 23.00
        $this->assertSame('15.00', $res['operating_expenses']['total']);
        $this->assertSame('8.00', $res['technician_commissions']['total']);
        $this->assertSame('23.00', $res['operating_costs']['total']);

        // Net profit: 80.00 - 23.00 = 57.00 (margin: 57 / 140 = 40.71%)
        $this->assertSame('57.00', $res['net_profit']['total']);
        $this->assertEquals(40.71, $res['net_profit']['margin_percent']);
        $this->assertFalse($res['net_profit']['is_loss']);

        // Top materials
        $this->assertCount(1, $res['top_materials']);
        $this->assertSame('شاشة آيفون', $res['top_materials'][0]['name']);
        $this->assertSame('40.00', $res['top_materials'][0]['profit']);

        // Expense categories
        $this->assertCount(1, $res['operating_expenses']['by_category']);
        $this->assertSame('كهرباء ومياه', $res['operating_expenses']['by_category'][0]['name']);
        $this->assertSame('15.00', $res['operating_expenses']['by_category'][0]['total']);

        // Daily breakdown contains active days
        $this->assertCount(3, $res['by_day']);
    }

    public function test_profit_summary_handles_empty_period_and_losses(): void
    {
        // 1. Empty period
        $res = $this->getJson('/api/reports/profit-summary?from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->json('data');

        $this->assertSame('0.00', $res['revenue']['total']);
        $this->assertSame('0.00', $res['cogs']['total']);
        $this->assertSame('0.00', $res['gross_profit']['total']);
        $this->assertSame('0.00', $res['net_profit']['total']);
        $this->assertEquals(0, $res['gross_profit']['margin_percent']);
        $this->assertEquals(0, $res['net_profit']['margin_percent']);
        $this->assertFalse($res['net_profit']['is_loss']);
        $this->assertCount(0, $res['by_day']);

        // 2. Loss period: only expenses
        $category = ExpenseCategory::query()->create(['name' => 'إيجار']);
        Expense::query()->create([
            'expense_no' => 'EXP-0002',
            'expense_category_id' => $category->id,
            'amount' => '500.00',
            'payment_method' => 'CASH',
            'expense_date' => '2026-07-15',
            'status' => 'POSTED',
            'recorded_by' => $this->user->id,
        ]);

        $res2 = $this->getJson('/api/reports/profit-summary?from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->json('data');

        $this->assertSame('-500.00', $res2['net_profit']['total']);
        $this->assertTrue($res2['net_profit']['is_loss']);
    }

    // ----------------------------------------------------------- receivables

    public function test_receivables_lists_customers_who_still_owe(): void
    {
        $owing = $this->customer('عميل مدين', opening: '100.00');
        $this->customerTransaction($owing, '2026-02-01', debit: '900.00');

        $settled = $this->customer('عميل مسدد', opening: '0.00');
        $this->customerTransaction($settled, '2026-02-01', debit: '500.00');
        $this->customerTransaction($settled, '2026-02-05', credit: '500.00');

        $body = $this->getJson('/api/reports/receivables')->assertOk()->json();

        $this->assertCount(1, $body['data']['rows']);
        $this->assertSame('عميل مدين', $body['data']['rows'][0]['name']);
        $this->assertSame('1000.00', $body['data']['rows'][0]['balance']);
        $this->assertSame('1000.00', $body['data']['totals']['balance']);
        $this->assertSame('1', $body['data']['totals']['parties']);
    }

    public function test_receivables_respects_an_as_of_date(): void
    {
        $customer = $this->customer('عميل', opening: '0.00');
        $this->customerTransaction($customer, '2026-02-01', debit: '900.00');
        $this->customerTransaction($customer, '2026-03-15', credit: '900.00');

        // As of February the customer still owes; today they do not.
        $february = $this->getJson('/api/reports/receivables?as_of=2026-02-28')->assertOk()->json('data');
        $this->assertSame('900.00', $february['rows'][0]['balance']);
        $this->assertSame('900.00', $february['totals']['balance']);

        $today = $this->getJson('/api/reports/receivables')->assertOk()->json('data');
        $this->assertCount(0, $today['rows']);
        $this->assertSame('0.00', $today['totals']['balance']);
    }

    public function test_receivables_can_include_settled_parties(): void
    {
        $customer = $this->customer('عميل مسدد', opening: '0.00');
        $this->customerTransaction($customer, '2026-02-01', debit: '500.00');
        $this->customerTransaction($customer, '2026-02-05', credit: '500.00');

        $data = $this->getJson('/api/reports/receivables?only_outstanding=0')->assertOk()->json('data');

        $this->assertCount(1, $data['rows']);
        $this->assertSame('0.00', $data['rows'][0]['balance']);
    }

    public function test_payables_flips_the_supplier_ledger_sign(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'مورد الدهانات',
            'opening_balance' => '0.00',
            'is_active' => true,
        ]);

        // A purchase credits the supplier ledger; the company owes 800.
        SupplierTransaction::query()->create([
            'supplier_id' => $supplier->id,
            'transaction_date' => '2026-02-01',
            'type' => TransactionType::PURCHASE_INVOICE,
            'debit' => '0.00',
            'credit' => '800.00',
            'created_by' => $this->user->id,
        ]);

        $data = $this->getJson('/api/reports/payables')->assertOk()->json('data');

        $this->assertCount(1, $data['rows']);
        $this->assertSame('800.00', $data['rows'][0]['balance']);
        $this->assertSame('800.00', $data['totals']['balance']);
    }

    // -------------------------------------------------------- stock movements

    public function test_stock_movements_accumulate_a_running_quantity_per_material(): void
    {
        $material = $this->material('كابل', stock: '80.000', min: '0.000');

        $this->movement($material, '2026-01-05', 'PURCHASE', 'IN', '100.000');
        $this->movement($material, '2026-01-08', 'SALE', 'OUT', '30.000');
        $this->movement($material, '2026-01-12', 'PURCHASE', 'IN', '10.000');

        $body = $this->getJson("/api/reports/stock-movements?material_id={$material->id}")
            ->assertOk()->json();

        $rows = $body['data']['rows'];
        $this->assertTrue($body['data']['has_running_quantity']);
        $this->assertSame('100.000', $rows[0]['running_quantity']);
        $this->assertSame('-30.000', $rows[1]['signed_quantity']);
        $this->assertSame('70.000', $rows[1]['running_quantity']);
        $this->assertSame('80.000', $rows[2]['running_quantity']);
        $this->assertSame('شراء', $rows[0]['type_label']);
    }

    public function test_stock_movement_running_quantity_continues_across_pages(): void
    {
        $material = $this->material('بلاط', stock: '0.000', min: '0.000');

        for ($i = 1; $i <= 6; $i++) {
            $this->movement($material, '2026-02-0'.$i, 'PURCHASE', 'IN', '10.000');
        }

        $second = $this->getJson("/api/reports/stock-movements?material_id={$material->id}&per_page=3&page=2")
            ->assertOk()->json();

        // Page 1 ended at 30; page 2 must start from there.
        $this->assertSame('40.000', $second['data']['rows'][0]['running_quantity']);
        $this->assertSame('60.000', $second['data']['rows'][2]['running_quantity']);
        $this->assertSame(6, $second['meta']['total']);
    }

    public function test_stock_movements_without_a_material_omit_the_running_quantity(): void
    {
        $a = $this->material('مادة أ', stock: '0.000', min: '0.000');
        $b = $this->material('مادة ب', stock: '0.000', min: '0.000');
        $this->movement($a, '2026-01-05', 'PURCHASE', 'IN', '5.000');
        $this->movement($b, '2026-01-06', 'SALE', 'OUT', '2.000');

        $body = $this->getJson('/api/reports/stock-movements')->assertOk()->json();

        $this->assertFalse($body['data']['has_running_quantity']);
        $this->assertNull($body['data']['rows'][0]['running_quantity']);
        // Newest first when not accumulating.
        $this->assertSame('2026-01-06', $body['data']['rows'][0]['movement_date']);
    }

    public function test_stock_movements_filter_by_type(): void
    {
        $material = $this->material('مادة', stock: '0.000', min: '0.000');
        $this->movement($material, '2026-01-05', 'PURCHASE', 'IN', '5.000');
        $this->movement($material, '2026-01-06', 'SALE', 'OUT', '2.000');

        $rows = $this->getJson('/api/reports/stock-movements?type=SALE')->assertOk()->json('data.rows');

        $this->assertCount(1, $rows);
        $this->assertSame('SALE', $rows[0]['type']);
    }

    public function test_it_rejects_an_unknown_movement_type(): void
    {
        $this->getJson('/api/reports/stock-movements?type=NOPE')
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    // ------------------------------------------------------------- security

    public function test_every_report_requires_the_reports_permission(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            '/api/reports/low-stock',
            '/api/reports/sales-summary?from=2026-01-01&to=2026-01-31',
            '/api/reports/purchases-summary?from=2026-01-01&to=2026-01-31',
            '/api/reports/profit-summary?from=2026-01-01&to=2026-01-31',
            '/api/reports/receivables',
            '/api/reports/payables',
            '/api/reports/stock-movements',
        ] as $url) {
            $this->getJson($url)->assertForbidden();
        }
    }

    // ------------------------------------------------------------- factories

    protected function material(string $name, string $stock, string $min, string $sale = '0.00', ?string $sku = null): Material
    {
        return Material::factory()->create([
            'name' => $name,
            'sku' => $sku ?? strtoupper(substr(md5($name), 0, 8)),
            'cost_price' => '1.00',
            'sale_price' => $sale,
            'min_stock' => $min,
            'stock_quantity' => $stock,
        ]);
    }

    protected function customer(string $name, string $opening = '0.00'): Customer
    {
        return Customer::factory()->create(['name' => $name, 'opening_balance' => $opening]);
    }

    protected function customerTransaction(Customer $customer, string $date, string $debit = '0.00', string $credit = '0.00'): void
    {
        CustomerTransaction::query()->create([
            'customer_id' => $customer->id,
            'transaction_date' => $date,
            'type' => TransactionType::SALES_INVOICE,
            'debit' => $debit,
            'credit' => $credit,
            'created_by' => $this->user->id,
        ]);
    }

    protected function salesInvoice(
        Customer $customer,
        string $date,
        string $total,
        string $paid = '0.00',
        string $remaining = '0.00',
        DocumentStatus $status = DocumentStatus::POSTED,
    ): SalesInvoice {
        return SalesInvoice::query()->create([
            'invoice_no' => 'SI-'.str_pad((string) (SalesInvoice::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'customer_id' => $customer->id,
            'invoice_date' => $date,
            'status' => $status,
            'subtotal' => $total,
            'total' => $total,
            'paid_amount' => $paid,
            'remaining' => $remaining,
            'created_by' => $this->user->id,
        ]);
    }

    protected function movement(Material $material, string $date, string $type, string $direction, string $quantity): void
    {
        StockMovement::query()->create([
            'material_id' => $material->id,
            'type' => $type,
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_price' => '1.00',
            'source_type' => 'MANUAL',
            'movement_date' => $date,
            'created_by' => $this->user->id,
        ]);
    }
}
