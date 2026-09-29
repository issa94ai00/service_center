<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\MovementDirection;
use App\Enums\MovementType;
use App\Enums\TransactionType;
use App\Exceptions\DocumentLockedException;
use App\Models\Material;
use App\Models\PurchaseInvoice;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A purchase runs the opposite way to a sale: stock comes IN and the supplier
 * ledger is credited, because the company now owes for the goods.
 */
class PurchaseInvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoices;

    protected User $user;

    protected Supplier $supplier;

    protected Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->create([
            'key' => 'purchase_prefix', 'value' => 'PR-', 'group' => 'numbers', 'type' => 'string',
        ]);

        $this->invoices = app(InvoiceService::class);
        $this->user = User::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->material = Material::factory()->inStock('10.000')->create();
    }

    public function test_a_draft_purchase_holds_totals_but_moves_nothing(): void
    {
        $invoice = $this->draft([$this->line('100', '18.00')]);

        $this->assertSame(DocumentStatus::DRAFT, $invoice->status);
        $this->assertNull($invoice->invoice_no);
        $this->assertSame('1800.00', $invoice->total);
        $this->assertSame('10.000', $this->material->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_posting_increments_stock_and_records_an_in_movement(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->line('100', '18.00')]));

        $this->assertSame('PR-000001', $invoice->invoice_no);
        $this->assertSame('110.000', $this->material->fresh()->stock_quantity);

        $movement = StockMovement::query()->sole();
        $this->assertSame(MovementType::PURCHASE, $movement->type);
        $this->assertSame(MovementDirection::IN, $movement->direction);
        $this->assertSame('100.000', $movement->quantity);
        $this->assertSame('18.00', $movement->unit_price);
    }

    public function test_posting_credits_the_supplier_ledger(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->line('100', '18.00')]));

        $txn = SupplierTransaction::query()->sole();
        $this->assertSame(TransactionType::PURCHASE_INVOICE, $txn->type);
        // A purchase is money owed to the supplier, so it lands on the credit side.
        $this->assertSame('1800.00', $txn->credit);
        $this->assertSame('0.00', $txn->debit);
        $this->assertSame($invoice->id, $txn->reference_id);
    }

    public function test_a_purchase_discount_reduces_the_amount_owed(): void
    {
        $invoice = $this->postInvoice($this->draft(
            [$this->line('100', '18.00')],
            discountType: 'PERCENT',
            discountValue: '10',
        ));

        $this->assertSame('1800.00', $invoice->subtotal);
        $this->assertSame('180.00', $invoice->discount_amount);
        $this->assertSame('1620.00', $invoice->total);
        $this->assertSame('1620.00', SupplierTransaction::query()->sole()->credit);
    }

    public function test_cancelling_removes_the_stock_it_added(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->line('100', '18.00')]));
        $this->assertSame('110.000', $this->material->fresh()->stock_quantity);

        $this->invoices->cancelPurchase($invoice, $this->user->id, 'مرتجع');

        $this->assertSame('10.000', $this->material->fresh()->stock_quantity);

        $reversal = StockMovement::query()->whereNotNull('cancels_id')->sole();
        $this->assertSame(MovementDirection::OUT, $reversal->direction);
        $this->assertSame(MovementType::PURCHASE_RETURN, $reversal->type);
    }

    public function test_cancelling_clears_the_supplier_balance(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->line('100', '18.00')]));

        $this->invoices->cancelPurchase($invoice, $this->user->id, 'مرتجع');

        $net = SupplierTransaction::query()->sum('credit') - SupplierTransaction::query()->sum('debit');
        $this->assertEquals(0, $net);
        $this->assertSame(DocumentStatus::CANCELLED, $invoice->fresh()->status);
    }

    public function test_a_posted_purchase_cannot_be_posted_or_deleted_again(): void
    {
        $invoice = $this->postInvoice($this->draft([$this->line('10', '18.00')]));

        $this->expectException(DocumentLockedException::class);

        $this->invoices->postPurchase($invoice, $this->user->id);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    protected function draft(array $lines, string $discountType = 'NONE', string $discountValue = '0'): PurchaseInvoice
    {
        return $this->invoices->savePurchaseDraft(new PurchaseInvoice, [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => '2026-05-10',
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'items' => $lines,
        ], $this->user->id);
    }

    protected function postInvoice(PurchaseInvoice $invoice): PurchaseInvoice
    {
        return $this->invoices->postPurchase($invoice, $this->user->id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function line(string $quantity, string $price): array
    {
        return [
            'material_id' => $this->material->id,
            'quantity' => $quantity,
            'unit_price' => $price,
        ];
    }
}
