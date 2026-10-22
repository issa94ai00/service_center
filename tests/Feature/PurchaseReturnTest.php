<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\MovementDirection;
use App\Enums\MovementSourceType;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Material;
use App\Models\Permission;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stock sent back to a supplier: it leaves the shelf, comes off what is owed
 * on the invoice, and cancelling the return puts both back.
 */
class PurchaseReturnTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoices;

    protected ReturnService $returns;

    protected User $user;

    protected Supplier $supplier;

    protected Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoices = app(InvoiceService::class);
        $this->returns = app(ReturnService::class);
        $this->user = User::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->material = Material::factory()->inStock('0.000')->create();
    }

    public function test_a_purchase_return_removes_stock_and_reduces_what_is_owed(): void
    {
        $invoice = $this->postedInvoice('10', '15.00');

        $return = $this->returnLine($invoice, '4');

        $this->assertStringStartsWith('PRT-', $return->return_no);
        $this->assertSame('60.00', $return->total);
        $this->assertSame('60.00', $return->applied_amount);
        $this->assertSame('6.000', $this->material->fresh()->stock_quantity);

        $invoice->refresh();
        $this->assertSame('90.00', $invoice->remaining);
        $this->assertSame('60.00', $invoice->returned_amount);

        $movement = StockMovement::query()->where('source_type', MovementSourceType::PURCHASE_RETURN)->sole();
        $this->assertSame(MovementDirection::OUT, $movement->direction);

        $debit = SupplierTransaction::query()->where('type', TransactionType::PURCHASE_RETURN)->sole();
        $this->assertSame('60.00', $debit->debit);
        $this->assertSame('90.00', $this->supplierBalance());
    }

    public function test_goods_no_longer_on_the_shelf_cannot_be_returned_under_strict_stock(): void
    {
        $invoice = $this->postedInvoice('5', '10.00');
        $this->material->update(['stock_quantity' => '2.000']);

        $this->expectException(InsufficientStockException::class);
        $this->returnLine($invoice, '3');
    }

    public function test_a_paid_invoice_can_take_cash_back_from_the_supplier(): void
    {
        $invoice = $this->postedInvoice('2', '50.00');
        $invoice->update(['paid_amount' => '100.00', 'remaining' => '0.00']);

        $return = $this->returnLine($invoice, '1', 'REFUND');

        $this->assertSame('0.00', $return->applied_amount);
        $this->assertSame('50.00', $return->refunded_amount);

        $refund = SupplierTransaction::query()->where('type', TransactionType::SUPPLIER_REFUND)->sole();
        $this->assertSame('50.00', $refund->credit);
    }

    public function test_cancelling_a_purchase_return_restores_stock_and_balance(): void
    {
        $invoice = $this->postedInvoice('10', '15.00');
        $return = $this->returnLine($invoice, '4');

        $cancelled = $this->returns->cancelPurchaseReturn($return, $this->user->id, 'المورد رفض الاستلام');

        $this->assertSame(DocumentStatus::CANCELLED, $cancelled->status);
        $this->assertSame('10.000', $this->material->fresh()->stock_quantity);
        $this->assertSame('150.00', $invoice->fresh()->remaining);
        $this->assertSame('150.00', $this->supplierBalance());
    }

    public function test_an_invoice_with_a_standing_return_cannot_be_cancelled(): void
    {
        $invoice = $this->postedInvoice('3', '10.00');
        $this->returnLine($invoice, '1');

        $this->expectException(BusinessRuleException::class);
        $this->invoices->cancelPurchase($invoice->fresh(), $this->user->id, 'إلغاء');
    }

    public function test_the_api_records_and_cancels_a_return(): void
    {
        $invoice = $this->postedInvoice('3', '10.00');
        $this->actingAs($this->userWithPermissions(['purchases.view', 'purchases.return']));

        $this->getJson("/api/purchases/{$invoice->id}/returnable")
            ->assertOk()
            ->assertJsonPath('data.lines.0.returnable_quantity', '3.000');

        $id = $this->postJson("/api/purchases/{$invoice->id}/returns", [
            'reason' => 'غير مطابق',
            'items' => [['purchase_invoice_item_id' => $invoice->items[0]->id, 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('data.total', '20.00')->json('data.id');

        $this->postJson("/api/purchase-returns/{$id}/cancel", ['reason' => 'خطأ إدخال'])
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');
    }

    // ---------------------------------------------------------------- helpers

    protected function postedInvoice(string $quantity, string $price): PurchaseInvoice
    {
        $draft = $this->invoices->savePurchaseDraft(new PurchaseInvoice, [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => '2026-05-10',
            'items' => [['material_id' => $this->material->id, 'quantity' => $quantity, 'unit_price' => $price]],
        ], $this->user->id);

        return $this->invoices->postPurchase($draft, $this->user->id)->load('items');
    }

    protected function returnLine(PurchaseInvoice $invoice, string $quantity, string $settlement = 'CREDIT'): PurchaseReturn
    {
        return $this->returns->createPurchaseReturn($invoice, [
            'reason' => 'تالف',
            'settlement' => $settlement,
            'items' => [['purchase_invoice_item_id' => $invoice->items[0]->id, 'quantity' => $quantity]],
        ], $this->user->id);
    }

    protected function supplierBalance(): string
    {
        $row = SupplierTransaction::query()->where('supplier_id', $this->supplier->id)
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as balance')->first();

        return number_format((float) $row->balance, 2, '.', '');
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
