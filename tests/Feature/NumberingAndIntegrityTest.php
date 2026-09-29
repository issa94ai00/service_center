<?php

namespace Tests\Feature;

use App\Enums\LineType;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Material;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NumberingAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected NumberingService $numbers;

    protected InvoiceService $invoices;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->numbers = app(NumberingService::class);
        $this->invoices = app(InvoiceService::class);
        $this->user = User::factory()->create();
    }

    // ---------------------------------------------------------------- numbering

    public function test_numbers_are_zero_padded_and_prefixed_from_settings(): void
    {
        Setting::query()->create([
            'key' => 'invoice_prefix', 'value' => 'SL-', 'group' => 'numbers', 'type' => 'string',
        ]);

        $this->assertSame('SL-000001', $this->numbers->next('SALES_INVOICE'));
        $this->assertSame('SL-000002', $this->numbers->next('SALES_INVOICE'));
    }

    public function test_each_document_type_keeps_its_own_counter(): void
    {
        foreach ([['invoice_prefix', 'SL-'], ['purchase_prefix', 'PR-'], ['payment_prefix', 'PAY-']] as [$key, $value]) {
            Setting::query()->create(['key' => $key, 'value' => $value, 'group' => 'numbers', 'type' => 'string']);
        }

        $this->assertSame('SL-000001', $this->numbers->next('SALES_INVOICE'));
        $this->assertSame('PR-000001', $this->numbers->next('PURCHASE_INVOICE'));
        $this->assertSame('PAY-000001', $this->numbers->next('PAYMENT'));
        $this->assertSame('SL-000002', $this->numbers->next('SALES_INVOICE'));
    }

    public function test_repair_invoices_number_apart_from_counter_sales(): void
    {
        // repair_invoice_prefix is seeded by the migration as RS-.

        $this->assertSame('SL-000001', $this->numbers->next('SALES_INVOICE'));
        $this->assertSame('RS-000001', $this->numbers->next('REPAIR_INVOICE'));
        $this->assertSame('RS-000002', $this->numbers->next('REPAIR_INVOICE'));
        // The counter-sales series is untouched by the repair settlements.
        $this->assertSame('SL-000002', $this->numbers->next('SALES_INVOICE'));
    }

    public function test_the_counter_is_persisted_rather_than_derived_from_row_counts(): void
    {
        $this->numbers->next('SALES_INVOICE');
        $this->numbers->next('SALES_INVOICE');

        $sequence = DocumentSequence::query()->where('document_type', 'SALES_INVOICE')->sole();

        // Deriving the next number from COUNT(*) would reuse a number after a
        // draft is deleted; a stored counter never goes backwards.
        $this->assertSame(2, (int) $sequence->last_value);
    }

    public function test_a_posted_counter_sale_stays_on_the_sales_series(): void
    {
        Setting::query()->create(['key' => 'invoice_prefix', 'value' => 'SL-', 'group' => 'numbers', 'type' => 'string']);

        $customer = Customer::factory()->create();
        $material = Material::factory()->inStock('5.000')->create();

        $draft = $this->invoices->saveSalesDraft(new SalesInvoice, [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-05-10',
            'items' => [
                ['line_type' => LineType::MATERIAL, 'material_id' => $material->id, 'quantity' => '1', 'unit_price' => '10.00'],
            ],
        ], $this->user->id);

        $posted = $this->invoices->postSales($draft, $this->user->id);

        // Only a settlement invoice tags itself with a repair; counter sales
        // keep the plain sales series and stay untagged.
        $this->assertSame('SL-000001', $posted->invoice_no);
        $this->assertNull($posted->repair_id);
    }

    public function test_a_number_is_never_reissued_after_a_gap(): void
    {
        $first = $this->numbers->next('SALES_INVOICE');
        DocumentSequence::query()->where('document_type', 'SALES_INVOICE')->update(['last_value' => 99]);
        $next = $this->numbers->next('SALES_INVOICE');

        $this->assertSame('SL-000001', $first);
        $this->assertSame('SL-000100', $next);
    }

    // ------------------------------------------------------------- transactions

    public function test_a_failed_draft_save_leaves_no_partial_invoice_behind(): void
    {
        $customer = Customer::factory()->create();
        $material = Material::factory()->inStock('100.000')->create();

        // The second line points at a material that does not exist, so its
        // INSERT fails on the foreign key — after the invoice row and the first
        // item have already been written. Arithmetic validation runs before any
        // insert, so only a mid-write failure like this exercises the rollback.
        try {
            $this->invoices->saveSalesDraft(new SalesInvoice, [
                'customer_id' => $customer->id,
                'invoice_date' => '2026-05-10',
                'items' => [
                    ['line_type' => LineType::MATERIAL, 'material_id' => $material->id, 'quantity' => '1', 'unit_price' => '100.00'],
                    ['line_type' => LineType::MATERIAL, 'material_id' => $material->id + 9999, 'quantity' => '1', 'unit_price' => '10.00'],
                ],
            ], $this->user->id);

            $this->fail('The invalid line should have aborted the save.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame(0, SalesInvoice::query()->count(), 'A half-written invoice survived a failed save.');
        $this->assertSame(0, SalesInvoiceItem::query()->count());
    }

    public function test_posting_is_atomic_across_stock_ledger_and_document(): void
    {
        $customer = Customer::factory()->create();
        $material = Material::factory()->inStock('5.000')->create();

        $draft = $this->invoices->saveSalesDraft(new SalesInvoice, [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-05-10',
            'items' => [
                ['line_type' => LineType::MATERIAL, 'material_id' => $material->id, 'quantity' => '1', 'unit_price' => '10.00'],
                // Second line outruns the stock, so the whole post must roll back.
                ['line_type' => LineType::MATERIAL, 'material_id' => $material->id, 'quantity' => '50', 'unit_price' => '10.00'],
            ],
        ], $this->user->id);

        try {
            $this->invoices->postSales($draft, $this->user->id);
            $this->fail('Posting should have been refused for insufficient stock.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame('5.000', $material->fresh()->stock_quantity);
        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame(0, DB::table('customer_transactions')->count());
        $this->assertNull($draft->fresh()->invoice_no);
    }

    // ------------------------------------------------------- inventory policy

    public function test_the_configured_inventory_policy_is_actually_read(): void
    {
        Setting::query()->create([
            'key' => 'inventory_sale_policy', 'value' => 'permissive', 'group' => 'financial', 'type' => 'string',
        ]);

        $customer = Customer::factory()->create();
        $material = Material::factory()->inStock('1.000')->create();

        $draft = $this->invoices->saveSalesDraft(new SalesInvoice, [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-05-10',
            'items' => [
                ['line_type' => LineType::MATERIAL, 'material_id' => $material->id, 'quantity' => '10', 'unit_price' => '10.00'],
            ],
        ], $this->user->id);

        // Under 'permissive' the sale is allowed to drive stock negative.
        $this->invoices->postSales($draft, $this->user->id);

        $this->assertSame('-9.000', $material->fresh()->stock_quantity);
    }

    public function test_the_default_policy_remains_strict(): void
    {
        $customer = Customer::factory()->create();
        $material = Material::factory()->inStock('1.000')->create();

        $draft = $this->invoices->saveSalesDraft(new SalesInvoice, [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-05-10',
            'items' => [
                ['line_type' => LineType::MATERIAL, 'material_id' => $material->id, 'quantity' => '10', 'unit_price' => '10.00'],
            ],
        ], $this->user->id);

        $this->expectException(\App\Exceptions\InsufficientStockException::class);

        $this->invoices->postSales($draft, $this->user->id);
    }
}
