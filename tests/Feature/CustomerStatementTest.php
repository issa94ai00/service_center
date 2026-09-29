<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerStatementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $role = Role::query()->create(['name' => 'Accountant', 'slug' => 'accountant']);
        $permission = Permission::query()->create([
            'name' => 'عرض كشوف الحسابات',
            'slug' => 'statements.view',
            'group' => 'statements',
        ]);
        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);

        $this->customer = Customer::query()->create([
            'name' => 'شركة الإكساء الحديث',
            'phone' => '0999000111',
            'opening_balance' => '100.00',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    public function test_it_builds_a_running_balance_from_the_opening_balance(): void
    {
        $this->transaction('2026-01-05', TransactionType::SALES_INVOICE, debit: '1000.00');
        $this->transaction('2026-01-10', TransactionType::CUSTOMER_PAYMENT, credit: '400.00');

        $response = $this->getJson("/api/customers/{$this->customer->id}/statement")
            ->assertOk();

        $data = $response->json('data');

        // 100 opening + 1000 invoice - 400 payment
        $this->assertSame('100.00', $data['opening_balance']);
        $this->assertSame('1100.00', $data['rows'][0]['running_balance']);
        $this->assertSame('700.00', $data['rows'][1]['running_balance']);
        $this->assertSame('1000.00', $data['totals']['debit']);
        $this->assertSame('400.00', $data['totals']['credit']);
        $this->assertSame('700.00', $data['closing_balance']);
    }

    public function test_a_date_filter_carries_prior_movements_into_the_opening_balance(): void
    {
        $this->transaction('2026-01-05', TransactionType::SALES_INVOICE, debit: '1000.00');
        $this->transaction('2026-01-10', TransactionType::CUSTOMER_PAYMENT, credit: '400.00');
        $this->transaction('2026-02-03', TransactionType::SALES_INVOICE, debit: '250.00');

        $data = $this->getJson("/api/customers/{$this->customer->id}/statement?from=2026-02-01&to=2026-02-28")
            ->assertOk()
            ->json('data');

        // Everything before February collapses into the period opening balance.
        $this->assertSame('700.00', $data['opening_balance']);
        $this->assertCount(1, $data['rows']);
        $this->assertSame('250.00', $data['totals']['debit']);
        $this->assertSame('0.00', $data['totals']['credit']);
        $this->assertSame('950.00', $data['rows'][0]['running_balance']);
        $this->assertSame('950.00', $data['closing_balance']);
    }

    public function test_pagination_continues_the_running_balance_across_pages(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->transaction('2026-03-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), TransactionType::SALES_INVOICE, debit: '50.00');
        }

        $first = $this->getJson("/api/customers/{$this->customer->id}/statement?per_page=4")
            ->assertOk();

        $this->assertSame('100.00', $first->json('data.page_opening_balance'));
        $this->assertSame('300.00', $first->json('data.rows.3.running_balance'));
        $this->assertSame(3, $first->json('meta.last_page'));
        $this->assertSame(10, $first->json('meta.total'));

        $second = $this->getJson("/api/customers/{$this->customer->id}/statement?per_page=4&page=2")
            ->assertOk();

        // Page 2 picks up exactly where page 1 stopped.
        $this->assertSame('300.00', $second->json('data.page_opening_balance'));
        $this->assertSame('350.00', $second->json('data.rows.0.running_balance'));
        $this->assertSame('500.00', $second->json('data.rows.3.running_balance'));

        // Totals and closing balance describe the whole period, not the page.
        $this->assertSame('500.00', $second->json('data.totals.debit'));
        $this->assertSame('600.00', $second->json('data.closing_balance'));
    }

    public function test_an_empty_ledger_still_reports_the_opening_balance(): void
    {
        $data = $this->getJson("/api/customers/{$this->customer->id}/statement")
            ->assertOk()
            ->json('data');

        $this->assertSame([], $data['rows']);
        $this->assertSame('100.00', $data['opening_balance']);
        $this->assertSame('100.00', $data['page_opening_balance']);
        $this->assertSame('100.00', $data['closing_balance']);
        $this->assertSame('0.00', $data['totals']['debit']);
    }

    public function test_a_transaction_on_the_last_day_of_the_range_is_included(): void
    {
        $this->transaction('2026-02-28', TransactionType::SALES_INVOICE, debit: '120.00');

        $data = $this->getJson("/api/customers/{$this->customer->id}/statement?from=2026-02-01&to=2026-02-28")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data['rows']);
        $this->assertSame('120.00', $data['totals']['debit']);
        $this->assertSame('220.00', $data['closing_balance']);
    }

    public function test_it_rejects_a_reversed_date_range(): void
    {
        $this->getJson("/api/customers/{$this->customer->id}/statement?from=2026-05-01&to=2026-04-01")
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }

    public function test_it_denies_a_user_without_the_statements_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson("/api/customers/{$this->customer->id}/statement")
            ->assertForbidden();
    }

    public function test_it_resolves_document_numbers_without_a_query_per_row(): void
    {
        $invoices = collect(range(1, 3))->map(fn (int $i) => SalesInvoice::query()->create([
            'invoice_no' => 'SI-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-04-0'.$i,
            'status' => 'POSTED',
            'subtotal' => '100.00',
            'total' => '100.00',
            'remaining' => '100.00',
            'created_by' => $this->user->id,
        ]));

        foreach ($invoices as $invoice) {
            $this->transaction($invoice->invoice_date->format('Y-m-d'), TransactionType::SALES_INVOICE, debit: '100.00')
                ->update([
                    'reference_type' => SalesInvoice::class,
                    'reference_id' => $invoice->id,
                ]);
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $data = $this->getJson("/api/customers/{$this->customer->id}/statement")
            ->assertOk()
            ->json('data');

        $this->assertSame('SI-0001', $data['rows'][0]['reference_no']);
        $this->assertSame('SI-0003', $data['rows'][2]['reference_no']);
        $this->assertSame(['kind' => 'sales_invoice', 'id' => $invoices[0]->id], $data['rows'][0]['reference']);

        // Reference numbers resolve in one batched query per reference type,
        // so adding ledger rows must not add queries.
        $this->assertGreaterThan(0, $queries);
        $this->assertLessThan(10, $queries, "Statement ran {$queries} queries; expected a constant, batched count.");
    }

    public function test_a_supplier_statement_mirrors_the_customer_statement(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'مورد مواد البناء',
            'phone' => '0988000222',
            'opening_balance' => '50.00',
            'is_active' => true,
        ]);

        SupplierTransaction::query()->create([
            'supplier_id' => $supplier->id,
            'transaction_date' => '2026-01-15',
            'type' => TransactionType::PURCHASE_INVOICE,
            'debit' => '0.00',
            'credit' => '800.00',
            'created_by' => $this->user->id,
        ]);

        SupplierTransaction::query()->create([
            'supplier_id' => $supplier->id,
            'transaction_date' => '2026-01-20',
            'type' => TransactionType::SUPPLIER_PAYMENT,
            'debit' => '300.00',
            'credit' => '0.00',
            'created_by' => $this->user->id,
        ]);

        $data = $this->getJson("/api/suppliers/{$supplier->id}/statement")
            ->assertOk()
            ->json('data');

        $this->assertSame('مورد مواد البناء', $data['party']['name']);
        $this->assertSame('50.00', $data['opening_balance']);
        $this->assertSame('-750.00', $data['rows'][0]['running_balance']);
        $this->assertSame('-450.00', $data['closing_balance']);
        $this->assertSame('سند دفع', $data['rows'][1]['type_label']);
    }

    protected function transaction(string $date, TransactionType $type, string $debit = '0.00', string $credit = '0.00'): CustomerTransaction
    {
        return CustomerTransaction::query()->create([
            'customer_id' => $this->customer->id,
            'transaction_date' => $date,
            'type' => $type,
            'debit' => $debit,
            'credit' => $credit,
            'description' => 'حركة اختبار',
            'created_by' => $this->user->id,
        ]);
    }
}
