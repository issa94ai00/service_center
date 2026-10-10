<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\LineType;
use App\Enums\PaymentType;
use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الدولار عملة الدفاتر، وبقية العملات تُدار بأسعار صرف: السند بالليرة يحفظ
 * ما سُلِّم وسعره، ويدخل الدفاتر بما يعادله بالدولار.
 */
class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['invoice_prefix', 'SL-'], ['payment_prefix', 'PAY-']] as [$key, $value]) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'numbers', 'type' => 'string']);
        }

        $this->customer = Customer::factory()->create();
    }

    // ------------------------------------------------------------- management

    public function test_usd_is_the_seeded_base_currency(): void
    {
        $this->actingAs($this->userWith([]));

        $this->getJson('/api/currencies/options')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'USD')
            ->assertJsonPath('data.0.is_base', true)
            ->assertJsonPath('data.0.exchange_rate', '1.000000');
    }

    public function test_managing_currencies_needs_its_permission(): void
    {
        $this->actingAs($this->userWith(['settings.manage']));

        $this->getJson('/api/currencies')->assertForbidden();
        $this->postJson('/api/currencies', $this->syp())->assertForbidden();
    }

    public function test_a_currency_is_added_with_its_first_rate_logged(): void
    {
        $this->actingAs($this->userWith(['currencies.manage']));

        $this->postJson('/api/currencies', ['code' => 'syp'] + $this->syp())
            ->assertCreated()
            ->assertJsonPath('data.code', 'SYP')
            ->assertJsonPath('data.exchange_rate', '13000.000000')
            ->assertJsonPath('data.decimals', 0);

        $this->assertSame(1, CurrencyRate::query()->count());
        $this->postJson('/api/currencies', $this->syp())->assertJsonValidationErrors('code');
    }

    public function test_changing_a_rate_is_logged_and_saving_it_unchanged_is_not(): void
    {
        $user = $this->userWith(['currencies.manage']);
        $this->actingAs($user);
        $syp = $this->createSyp();

        $this->putJson("/api/currencies/{$syp->id}", ['exchange_rate' => '13500'])->assertOk();
        $this->putJson("/api/currencies/{$syp->id}", ['exchange_rate' => '13500.000000', 'name' => 'ليرة'])->assertOk();

        $this->assertSame('13500.000000', $syp->fresh()->exchange_rate);
        $this->assertSame('ليرة', $syp->fresh()->name);

        $this->getJson("/api/currencies/{$syp->id}/rates")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.previous_rate', '13000.000000')
            ->assertJsonPath('data.0.exchange_rate', '13500.000000')
            ->assertJsonPath('data.0.changed_by_name', $user->name);
    }

    public function test_the_base_currency_cannot_be_changed_or_deleted(): void
    {
        $this->actingAs($this->userWith(['currencies.manage']));
        $usd = Currency::query()->where('code', 'USD')->sole();

        $this->putJson("/api/currencies/{$usd->id}", ['exchange_rate' => '2'])->assertJsonValidationErrors('code');
        $this->putJson("/api/currencies/{$usd->id}", ['is_active' => false])->assertJsonValidationErrors('code');
        $this->deleteJson("/api/currencies/{$usd->id}")->assertJsonValidationErrors('code');

        $this->assertSame('1.000000', $usd->fresh()->exchange_rate);
        $this->assertTrue($usd->fresh()->is_active);
    }

    public function test_a_currency_in_use_is_deactivated_not_deleted(): void
    {
        $this->actingAs($this->userWith(['currencies.manage', 'expenses.create']));
        $syp = $this->createSyp();
        $unused = Currency::query()->create(['code' => 'EUR', 'name' => 'يورو', 'symbol' => '€', 'exchange_rate' => '0.9']);

        $this->recordExpense(['currency_code' => 'SYP', 'currency_amount' => '130000'])->assertCreated();

        $this->deleteJson("/api/currencies/{$syp->id}")->assertJsonValidationErrors('code');
        $this->deleteJson("/api/currencies/{$unused->id}")->assertOk();
        $this->assertModelMissing($unused);
    }

    public function test_the_options_list_only_active_currencies(): void
    {
        $this->actingAs($this->userWith([]));
        $this->createSyp(['is_active' => false]);

        $this->getJson('/api/currencies/options')->assertJsonCount(1, 'data');
    }

    // --------------------------------------------------------------- payments

    public function test_a_receipt_in_syp_reaches_the_books_in_usd(): void
    {
        $user = $this->userWith(['payments.create', 'payments.confirm']);
        $this->actingAs($user);
        $this->createSyp();
        $invoice = $this->salesInvoice('100.00', $user);

        $id = $this->postJson('/api/payments', [
            'payment_type' => 'CUSTOMER',
            'customer_id' => $this->customer->id,
            'currency_code' => 'SYP',
            'currency_amount' => '1300000',
            'exchange_rate' => '13000',
            'payment_date' => '2026-05-20',
            'allocations' => [['invoice_type' => 'SALES', 'sales_invoice_id' => $invoice->id, 'amount' => '100.00']],
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '100.00')
            ->assertJsonPath('data.currency_code', 'SYP')
            ->assertJsonPath('data.currency_amount', '1300000.00')
            ->assertJsonPath('data.exchange_rate', '13000.000000')
            ->json('data.id');

        $this->postJson("/api/payments/{$id}/post")->assertOk();

        $this->assertSame('0.00', $invoice->fresh()->remaining);

        $line = CustomerTransaction::query()->where('type', TransactionType::CUSTOMER_PAYMENT)->sole();
        $this->assertSame('100.00', $line->credit);
        $this->assertStringContainsString('1,300,000 ل.س × 13,000', $line->description);
    }

    public function test_the_usd_amount_is_rounded_to_the_cent(): void
    {
        $this->actingAs($this->userWith(['payments.create']));
        $this->createSyp();

        $this->postJson('/api/payments', $this->technicianPayout() + [
            'currency_code' => 'SYP',
            'currency_amount' => '100000',
        ])
            ->assertCreated()
            // 100000 / 13000 = 7.6923…
            ->assertJsonPath('data.amount', '7.69')
            ->assertJsonPath('data.exchange_rate', '13000.000000');
    }

    public function test_a_rate_that_moved_while_the_form_was_open_is_refused(): void
    {
        $this->actingAs($this->userWith(['payments.create']));
        $this->createSyp();

        $this->postJson('/api/payments', $this->technicianPayout() + [
            'currency_code' => 'SYP',
            'currency_amount' => '130000',
            'exchange_rate' => '12500',
        ])->assertConflict()->assertJsonPath('field', 'exchange_rate');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_foreign_amount_is_required_and_an_inactive_currency_refused(): void
    {
        $this->actingAs($this->userWith(['payments.create']));
        $this->createSyp(['is_active' => false]);

        $this->postJson('/api/payments', $this->technicianPayout() + [
            'currency_code' => 'SYP',
            'currency_amount' => '130000',
        ])->assertConflict()->assertJsonPath('field', 'currency_code');

        Currency::query()->where('code', 'SYP')->update(['is_active' => true]);

        $this->postJson('/api/payments', $this->technicianPayout() + [
            'currency_code' => 'SYP',
            'amount' => '10',
        ])->assertConflict()->assertJsonPath('field', 'currency_amount');
    }

    public function test_a_payment_without_a_currency_stays_usd(): void
    {
        $this->actingAs($this->userWith(['payments.create']));

        $this->postJson('/api/payments', $this->technicianPayout() + ['amount' => '25'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '25.00')
            ->assertJsonPath('data.currency_code', 'USD')
            ->assertJsonPath('data.currency_amount', '25.00');

        $this->assertNull(Payment::query()->sole()->currency_amount);
    }

    // --------------------------------------------------------------- expenses

    public function test_an_expense_in_syp_counts_in_usd_and_keeps_its_rate_on_edit(): void
    {
        $this->actingAs($this->userWith(['expenses.create', 'expenses.edit', 'expenses.view']));
        $syp = $this->createSyp();

        $id = $this->recordExpense(['currency_code' => 'SYP', 'currency_amount' => '650000'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '50.00')
            ->json('data.id');

        // The rate moves; correcting last week's expense keeps last week's rate.
        $syp->update(['exchange_rate' => '14000']);

        $this->putJson("/api/expenses/{$id}", [
            'currency_code' => 'SYP',
            'currency_amount' => '663000',
            'exchange_rate' => '13000',
            'notes' => 'تصحيح',
        ])
            ->assertOk()
            ->assertJsonPath('data.amount', '51.00')
            ->assertJsonPath('data.exchange_rate', '13000.000000');

        // Switching it to USD clears the foreign side.
        $this->putJson("/api/expenses/{$id}", ['currency_code' => 'USD', 'amount' => '40'])
            ->assertOk()
            ->assertJsonPath('data.amount', '40.00')
            ->assertJsonPath('data.currency_amount', '40.00');

        $this->assertNull(Expense::query()->find($id)->currency_amount);
    }

    // ---------------------------------------------------------------- printing

    public function test_documents_print_with_foreign_amounts(): void
    {
        $user = $this->userWith(['sales.view', 'payments.create', 'payments.view']);
        $this->actingAs($user);
        $this->createSyp(['show_on_invoices' => true]);
        $invoice = $this->salesInvoice('100.00', $user);

        $payment = Payment::query()->create([
            'payment_type' => PaymentType::CUSTOMER,
            'customer_id' => $this->customer->id,
            'amount' => '50.00',
            'currency_code' => 'SYP',
            'exchange_rate' => '13000',
            'currency_amount' => '650000',
            'payment_method' => 'CASH',
            'payment_date' => '2026-05-05',
            'status' => DocumentStatus::POSTED,
            'recorded_by' => $user->id,
        ]);

        $this->assertStringStartsWith('%PDF-', $this->get("/api/pdf/sales/{$invoice->id}")->assertOk()->getContent());
        $this->assertStringStartsWith('%PDF-', $this->get("/api/pdf/payments/{$payment->id}")->assertOk()->getContent());
        $this->assertStringStartsWith('%PDF-', $this->get("/api/pdf/payments/{$payment->id}?paper=80mm")->assertOk()->getContent());
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<string, mixed> */
    protected function syp(): array
    {
        return ['code' => 'SYP', 'name' => 'ليرة سورية', 'symbol' => 'ل.س', 'exchange_rate' => '13000', 'decimals' => 0];
    }

    /** @param  array<string, mixed>  $overrides */
    protected function createSyp(array $overrides = []): Currency
    {
        return Currency::query()->create([...$this->syp(), ...$overrides]);
    }

    /** @return array<string, mixed> */
    protected function technicianPayout(): array
    {
        $technician = \App\Models\Technician::query()->create(['name' => 'خبير', 'commission_rate' => 50]);

        return ['payment_type' => 'TECHNICIAN', 'technician_id' => $technician->id, 'payment_date' => '2026-05-20'];
    }

    /** @param  array<string, mixed>  $data */
    protected function recordExpense(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/expenses', [
            'expense_category_id' => ExpenseCategory::query()->firstOrFail()->id,
            'expense_date' => '2026-05-18',
            ...$data,
        ]);
    }

    protected function salesInvoice(string $total, User $user): SalesInvoice
    {
        $material = Material::factory()->inStock('10.000')->create();
        $invoices = app(InvoiceService::class);

        $draft = $invoices->saveSalesDraft(new SalesInvoice, [
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-05-10',
            'items' => [['line_type' => LineType::MATERIAL, 'material_id' => $material->id, 'quantity' => '1', 'unit_price' => $total]],
        ], $user->id);

        return $invoices->postSales($draft, $user->id);
    }

    /** @param  array<int, string>  $permissions */
    protected function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'دور '.$user->id, 'slug' => 'role-'.$user->id]);

        foreach ($permissions as $slug) {
            $role->permissions()->attach(
                Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => explode('.', $slug)[0]])
            );
        }

        $user->roles()->attach($role);

        return $user->fresh();
    }
}
