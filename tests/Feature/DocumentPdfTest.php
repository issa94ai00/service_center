<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\PaymentType;
use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PurchaseInvoice;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\ServiceUnit;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ArabicNumberToWords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentPdfTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->userWith(['sales.view', 'purchases.view', 'payments.create', 'statements.view']);

        Setting::query()->create([
            'key' => 'company_name', 'value' => 'شركة الإكساء', 'group' => 'general', 'type' => 'string',
        ]);

        $this->customer = Customer::query()->create([
            'name' => 'مؤسسة الأمل', 'opening_balance' => '0.00', 'is_active' => true,
        ]);

        $this->supplier = Supplier::query()->create([
            'name' => 'معمل الخشب', 'opening_balance' => '0.00', 'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    // ------------------------------------------------------------- documents

    public function test_it_streams_a_sales_invoice_as_an_inline_pdf(): void
    {
        $invoice = $this->salesInvoice();

        $response = $this->get("/api/pdf/sales/{$invoice->id}")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame(
            'inline; filename="sales-invoice-SL-0001.pdf"',
            $response->headers->get('Content-Disposition')
        );
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_it_renders_a_purchase_invoice(): void
    {
        $invoice = PurchaseInvoice::query()->create([
            'invoice_no' => 'PR-0001',
            'supplier_id' => $this->supplier->id,
            'purchase_date' => '2026-05-02',
            'status' => DocumentStatus::POSTED,
            'subtotal' => '900.00', 'total' => '900.00', 'remaining' => '900.00',
            'created_by' => $this->user->id,
        ]);

        $response = $this->get("/api/pdf/purchases/{$invoice->id}")->assertOk();

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('purchase-invoice-PR-0001.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_it_renders_a_payment_receipt(): void
    {
        $payment = Payment::query()->create([
            'payment_type' => PaymentType::CUSTOMER,
            'customer_id' => $this->customer->id,
            'amount' => '8500.00',
            'payment_method' => 'CASH',
            'reference_no' => 'PAY-0001',
            'payment_date' => '2026-05-05',
            'status' => DocumentStatus::POSTED,
            'recorded_by' => $this->user->id,
        ]);

        $response = $this->get("/api/pdf/payments/{$payment->id}")->assertOk();

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_it_renders_a_customer_statement_for_a_period(): void
    {
        CustomerTransaction::query()->create([
            'customer_id' => $this->customer->id,
            'transaction_date' => '2026-05-10',
            'type' => TransactionType::SALES_INVOICE,
            'debit' => '1000.00', 'credit' => '0.00',
            'created_by' => $this->user->id,
        ]);

        $response = $this->get("/api/pdf/customers/{$this->customer->id}/statement?from=2026-05-01&to=2026-05-31")
            ->assertOk();

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_it_renders_a_supplier_statement(): void
    {
        $this->get("/api/pdf/suppliers/{$this->supplier->id}/statement")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_document_with_no_number_falls_back_to_its_id(): void
    {
        $invoice = $this->salesInvoice(number: null, status: DocumentStatus::DRAFT);

        $response = $this->get("/api/pdf/sales/{$invoice->id}")->assertOk();

        $this->assertStringContainsString(
            "sales-invoice-{$invoice->id}.pdf",
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_the_statement_pdf_renders_the_whole_period_past_the_http_page_size(): void
    {
        // The JSON endpoint caps a page at 200 rows; the PDF must not stop there.
        for ($i = 0; $i < 250; $i++) {
            CustomerTransaction::query()->create([
                'customer_id' => $this->customer->id,
                'transaction_date' => '2026-05-10',
                'type' => TransactionType::SALES_INVOICE,
                'debit' => '10.00', 'credit' => '0.00',
                'created_by' => $this->user->id,
            ]);
        }

        $statement = app(\App\Services\StatementService::class)->forCustomer($this->customer, [
            'per_page' => \App\Services\DocumentPdfService::STATEMENT_ROW_LIMIT,
            'max_per_page' => \App\Services\DocumentPdfService::STATEMENT_ROW_LIMIT,
        ])['statement'];

        $this->assertCount(250, $statement['rows']);
        $this->assertSame('2500.00', $statement['closing_balance']);

        $this->get("/api/pdf/customers/{$this->customer->id}/statement")->assertOk();
    }

    public function test_the_json_statement_endpoint_still_caps_its_page_size(): void
    {
        $this->getJson("/api/customers/{$this->customer->id}/statement?per_page=3000")
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    // ------------------------------------------------------------- security

    public function test_pdf_access_follows_the_same_permissions_as_the_record(): void
    {
        $invoice = $this->salesInvoice();

        $payment = Payment::query()->create([
            'payment_type' => PaymentType::CUSTOMER,
            'customer_id' => $this->customer->id,
            'amount' => '10.00', 'payment_method' => 'CASH',
            'payment_date' => '2026-05-05', 'status' => DocumentStatus::POSTED,
            'recorded_by' => $this->user->id,
        ]);

        $this->actingAs($this->userWith([]));

        $this->get("/api/pdf/sales/{$invoice->id}")->assertForbidden();
        $this->get("/api/pdf/payments/{$payment->id}")->assertForbidden();
        $this->get("/api/pdf/customers/{$this->customer->id}/statement")->assertForbidden();
        $this->get("/api/pdf/suppliers/{$this->supplier->id}/statement")->assertForbidden();
    }

    public function test_a_guest_cannot_reach_a_pdf(): void
    {
        $invoice = $this->salesInvoice();

        auth()->logout();

        $this->get("/api/pdf/sales/{$invoice->id}")->assertUnauthorized();
    }

    public function test_it_rejects_a_reversed_statement_period(): void
    {
        $this->getJson("/api/pdf/customers/{$this->customer->id}/statement?from=2026-05-20&to=2026-05-01")
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }

    // -------------------------------------------------------- amount in words

    public function test_it_spells_amounts_in_arabic(): void
    {
        $this->assertSame('فقط ثمانية آلاف وخمسمئة ليرة سورية لا غير', ArabicNumberToWords::money('8500.00'));
        $this->assertSame('فقط ألفان ليرة سورية لا غير', ArabicNumberToWords::money(2000));
        $this->assertSame('فقط مئتان وخمسة وثمانون ألفًا ليرة سورية لا غير', ArabicNumberToWords::money(285000));
        // Hundreds take the singular, not the accusative: خمسمئة مليون.
        $this->assertSame('فقط ملياران وخمسمئة مليون ليرة سورية لا غير', ArabicNumberToWords::money(2_500_000_000));
        $this->assertSame('فقط صفر ليرة سورية لا غير', ArabicNumberToWords::money(0));
    }

    public function test_it_spells_the_fractional_part_separately(): void
    {
        $this->assertSame(
            'فقط ألف وخمسمئة ليرة سورية وخمسة وسبعون قرش لا غير',
            ArabicNumberToWords::money('1500.75')
        );
    }

    // ------------------------------------------------------------- factories

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

    protected function salesInvoice(?string $number = 'SL-0001', DocumentStatus $status = DocumentStatus::POSTED): SalesInvoice
    {
        $invoice = SalesInvoice::query()->create([
            'invoice_no' => $number,
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-05-02',
            'status' => $status,
            'subtotal' => '1000.00', 'total' => '1000.00', 'remaining' => '1000.00',
            'created_by' => $this->user->id,
        ]);

        $unit = ServiceUnit::factory()->meter()->create();

        $material = Material::factory()->inStock('100.000')->create([
            'name' => 'كابل كهربائي', 'sku' => 'CBL-1', 'unit_id' => $unit->id,
            'cost_price' => '5.00', 'sale_price' => '10.00',
        ]);

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'line_type' => 'MATERIAL',
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => '20.000', 'unit_price' => '50.00', 'line_total' => '1000.00',
        ]);

        return $invoice;
    }
}
