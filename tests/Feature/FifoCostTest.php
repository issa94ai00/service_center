<?php

namespace Tests\Feature;

use App\Enums\CostMethod;
use App\Models\Customer;
use App\Models\InventoryCostLayer;
use App\Models\Material;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\MaterialCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FifoCostTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoices;

    protected MaterialCostService $costs;

    protected User $user;

    protected Supplier $supplier;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ['key' => 'purchase_prefix', 'value' => 'PR-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'invoice_prefix', 'value' => 'SL-', 'group' => 'numbers', 'type' => 'string'],
        ] as $setting) {
            Setting::query()->create($setting);
        }

        $this->invoices = app(InvoiceService::class);
        $this->costs = app(MaterialCostService::class);
        $this->user = User::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->customer = Customer::factory()->create();
    }

    public function test_a_purchase_receives_a_cost_layer(): void
    {
        $material = $this->fifoMaterial();

        $this->purchase($material, '10', '20.00');

        $layer = InventoryCostLayer::query()->sole();
        $this->assertSame('10.000', $layer->remaining_quantity);
        $this->assertSame('20.00', $layer->unit_cost);
        $this->assertSame('20.00', $material->fresh()->cost_price);
    }

    public function test_a_sale_consumes_the_oldest_layer_first(): void
    {
        $material = $this->fifoMaterial();

        $this->purchase($material, '10', '20.00');
        $this->purchase($material, '10', '30.00');

        $this->sale($material, '4', '50.00');

        $item = SalesInvoiceItem::query()->sole();
        // Four units come out of the 20.00 layer.
        $this->assertSame('80.00', $item->cost_amount);
        $this->assertSame('6.000', $this->layerRemaining($material, 0));
    }

    public function test_a_sale_spanning_two_layers_uses_both(): void
    {
        $material = $this->fifoMaterial();

        $this->purchase($material, '10', '20.00');
        $this->purchase($material, '10', '30.00');

        $this->sale($material, '15', '50.00');

        // 10 × 20.00 + 5 × 30.00 = 350.00
        $this->assertSame('350.00', SalesInvoiceItem::query()->sole()->cost_amount);
        $this->assertSame('5.000', $this->layerRemaining($material, 1));
    }

    public function test_remaining_value_drives_the_current_cost(): void
    {
        $material = $this->fifoMaterial();

        $this->purchase($material, '10', '20.00');
        $this->purchase($material, '10', '30.00');
        $this->sale($material, '10', '50.00');

        // The 20.00 layer is spent, only the 30.00 layer remains.
        $this->assertSame('30.00', $material->fresh()->cost_price);
    }

    public function test_cancelling_a_purchase_removes_its_layer(): void
    {
        $material = $this->fifoMaterial();

        $invoice = $this->purchase($material, '10', '20.00');

        $this->invoices->cancelPurchase($invoice, $this->user->id, 'مرتجع');

        $this->assertSame('0.000', $this->layerRemaining($material, 0));
        $this->assertSame('0.000', $material->fresh()->stock_quantity);
    }

    public function test_switching_a_material_to_fifo_seeds_an_opening_layer(): void
    {
        $material = Material::factory()->inStock('8.000')->create(['cost_price' => '12.00']);
        $material->cost_method = CostMethod::FIFO;
        $material->save();

        $this->costs->seedFifoOpening($material->fresh());

        $layer = InventoryCostLayer::query()->sole();
        $this->assertSame('8.000', $layer->remaining_quantity);
        $this->assertSame('12.00', $layer->unit_cost);
    }

    // ------------------------------------------------------------------ helpers

    protected function fifoMaterial(): Material
    {
        return Material::factory()->create([
            'cost_method' => CostMethod::FIFO,
            'cost_price' => '0.00',
        ]);
    }

    protected function layerRemaining(Material $material, int $index): string
    {
        return (string) InventoryCostLayer::query()
            ->where('material_id', $material->id)
            ->orderBy('id')
            ->get()
            ->values()[$index]
            ->remaining_quantity;
    }

    protected function purchase(Material $material, string $quantity, string $price): PurchaseInvoice
    {
        $invoice = $this->invoices->savePurchaseDraft(new PurchaseInvoice, [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => '2026-05-10',
            'items' => [[
                'material_id' => $material->id,
                'quantity' => $quantity,
                'unit_price' => $price,
            ]],
        ], $this->user->id);

        return $this->invoices->postPurchase($invoice, $this->user->id);
    }

    protected function sale(Material $material, string $quantity, string $price): SalesInvoice
    {
        $invoice = $this->invoices->saveSalesDraft(new SalesInvoice, [
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-05-11',
            'items' => [[
                'line_type' => 'MATERIAL',
                'material_id' => $material->id,
                'quantity' => $quantity,
                'unit_price' => $price,
            ]],
        ], $this->user->id);

        return $this->invoices->postSales($invoice, $this->user->id);
    }
}
