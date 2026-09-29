<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * مصاريف الصندوق: تُسجَّل معتمدة لأن المال خرج قبل السند، وتُلغى بسبب مكتوب،
 * والملغى منها خارج كل مجموع.
 */
class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected ExpenseCategory $rent;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-20 10:00:00');

        // البادئة والأبواب الافتراضية تأتي من الهجرة نفسها، فلا تُزرع هنا:
        // اختبارها هكذا يختبر أيضاً أن التركيب الجديد يعمل بلا بذور.
        $this->assertSame('EXP-', Setting::get('expense_prefix'));

        $this->rent = ExpenseCategory::query()->where('name', 'إيجار المحل')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ------------------------------------------------------------- recording

    public function test_it_records_an_expense_posted_and_numbered(): void
    {
        $this->actingAs($this->userWith(['expenses.create']));

        $this->postJson('/api/expenses', [
            'expense_category_id' => $this->rent->id,
            'amount' => '150000',
            'payment_method' => 'CASH',
            'expense_date' => '2026-05-18',
            'payee' => 'صاحب المحل',
            'notes' => 'إيجار أيار',
        ])
            ->assertCreated()
            ->assertJsonPath('data.expense_no', 'EXP-000001')
            ->assertJsonPath('data.status', Expense::POSTED)
            ->assertJsonPath('data.amount', '150000.00');

        $this->assertDatabaseHas('expenses', [
            'expense_no' => 'EXP-000001',
            'expense_category_id' => $this->rent->id,
            'status' => Expense::POSTED,
            'payee' => 'صاحب المحل',
        ]);
    }

    public function test_the_series_runs_on_its_own_numbers(): void
    {
        $this->actingAs($this->userWith(['expenses.create']));

        $first = $this->record('1000')->json('data.expense_no');
        $second = $this->record('2000')->json('data.expense_no');

        $this->assertSame('EXP-000001', $first);
        $this->assertSame('EXP-000002', $second);
    }

    public function test_an_expense_defaults_to_today_and_to_cash(): void
    {
        $this->actingAs($this->userWith(['expenses.create']));

        $this->postJson('/api/expenses', [
            'expense_category_id' => $this->rent->id,
            'amount' => '500',
        ])
            ->assertCreated()
            ->assertJsonPath('data.expense_date', '2026-05-20')
            ->assertJsonPath('data.payment_method', 'CASH');
    }

    public function test_it_rejects_a_zero_or_negative_amount(): void
    {
        $this->actingAs($this->userWith(['expenses.create']));

        $this->postJson('/api/expenses', ['expense_category_id' => $this->rent->id, 'amount' => '0'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->postJson('/api/expenses', ['expense_category_id' => $this->rent->id, 'amount' => '-5'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');
    }

    public function test_an_expense_must_name_an_active_category(): void
    {
        $disabled = ExpenseCategory::query()->create(['name' => 'باب معطَّل', 'is_active' => false]);
        $this->actingAs($this->userWith(['expenses.create']));

        $this->postJson('/api/expenses', ['amount' => '500'])
            ->assertStatus(422)->assertJsonValidationErrors('expense_category_id');

        $this->postJson('/api/expenses', ['expense_category_id' => $disabled->id, 'amount' => '500'])
            ->assertStatus(422)->assertJsonValidationErrors('expense_category_id');
    }

    public function test_recording_requires_the_permission(): void
    {
        $this->actingAs($this->userWith(['expenses.view']));

        $this->postJson('/api/expenses', ['expense_category_id' => $this->rent->id, 'amount' => '500'])
            ->assertForbidden();
    }

    public function test_listing_requires_the_permission(): void
    {
        $this->actingAs($this->userWith([]));

        $this->getJson('/api/expenses')->assertForbidden();
    }

    // ---------------------------------------------------------------- edits

    public function test_a_posted_expense_can_be_corrected(): void
    {
        $this->actingAs($this->userWith(['expenses.create', 'expenses.edit']));
        $id = $this->record('1000')->json('data.id');

        $this->putJson("/api/expenses/{$id}", ['amount' => '1200', 'notes' => 'المبلغ الصحيح'])
            ->assertOk()
            ->assertJsonPath('data.amount', '1200.00')
            ->assertJsonPath('data.notes', 'المبلغ الصحيح')
            // التصحيح لا يمسّ رقم السند.
            ->assertJsonPath('data.expense_no', 'EXP-000001');
    }

    public function test_correcting_requires_the_edit_permission(): void
    {
        $this->actingAs($this->userWith(['expenses.create']));
        $id = $this->record('1000')->json('data.id');

        $this->putJson("/api/expenses/{$id}", ['amount' => '1200'])->assertForbidden();
    }

    public function test_a_cancelled_expense_cannot_be_corrected(): void
    {
        $this->actingAs($this->userWith(['expenses.create', 'expenses.edit', 'expenses.cancel']));
        $id = $this->record('1000')->json('data.id');

        $this->postJson("/api/expenses/{$id}/cancel", ['reason' => 'سُجّل مرتين'])->assertOk();

        $this->putJson("/api/expenses/{$id}", ['amount' => '1200'])->assertForbidden();
    }

    // ------------------------------------------------------------ cancelling

    public function test_cancelling_keeps_the_record_and_demands_a_reason(): void
    {
        $this->actingAs($this->userWith(['expenses.create', 'expenses.cancel']));
        $id = $this->record('1000')->json('data.id');

        $this->postJson("/api/expenses/{$id}/cancel", ['reason' => 'لا'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->postJson("/api/expenses/{$id}/cancel", ['reason' => 'سُجّل مرتين'])
            ->assertOk()
            ->assertJsonPath('data.status', Expense::CANCELLED)
            ->assertJsonPath('data.cancellation_reason', 'سُجّل مرتين');

        // السند باقٍ برقمه، لم يُحذف من السلسلة.
        $this->assertDatabaseHas('expenses', ['id' => $id, 'expense_no' => 'EXP-000001', 'status' => Expense::CANCELLED]);
    }

    public function test_an_expense_cannot_be_cancelled_twice(): void
    {
        $this->actingAs($this->userWith(['expenses.create', 'expenses.cancel']));
        $id = $this->record('1000')->json('data.id');

        $this->postJson("/api/expenses/{$id}/cancel", ['reason' => 'سُجّل مرتين'])->assertOk();
        $this->postJson("/api/expenses/{$id}/cancel", ['reason' => 'مرة أخرى'])->assertForbidden();
    }

    public function test_expenses_are_never_deleted(): void
    {
        $this->actingAs($this->userWith(['expenses.create', 'expenses.cancel', 'expenses.edit']));
        $id = $this->record('1000')->json('data.id');

        $this->deleteJson("/api/expenses/{$id}")->assertStatus(405);
    }

    // ------------------------------------------------------------- summaries

    public function test_the_summary_totals_what_was_filtered_and_ignores_the_cancelled(): void
    {
        $this->actingAs($this->userWith(['expenses.create', 'expenses.cancel', 'expenses.view']));

        $this->record('1000', '2026-05-20');
        $this->record('2500', '2026-05-02');
        $cancelled = $this->record('9999', '2026-05-10')->json('data.id');
        $this->postJson("/api/expenses/{$cancelled}/cancel", ['reason' => 'مبلغ خاطئ'])->assertOk();

        $summary = $this->getJson('/api/expenses?from=2026-05-01&to=2026-05-31')
            ->assertOk()->json('summary');

        $this->assertSame('3500.00', $summary['filtered']['amount']);
        $this->assertSame(2, $summary['filtered']['count']);
        $this->assertSame('1000.00', $summary['today']);
        $this->assertSame('3500.00', $summary['month']);

        // الملغى يبقى ظاهراً في القائمة وإن خرج من المجاميع.
        $this->assertCount(3, $this->getJson('/api/expenses')->json('data'));
    }

    public function test_the_summary_follows_the_filter(): void
    {
        $this->actingAs($this->userWith(['expenses.create', 'expenses.view']));
        $fuel = ExpenseCategory::query()->create(['name' => 'مواصلات']);

        $this->record('1000', '2026-05-20');
        $this->record('700', '2026-05-20', $fuel->id);

        $summary = $this->getJson("/api/expenses?expense_category_id={$fuel->id}")
            ->assertOk()->json('summary');

        $this->assertSame('700.00', $summary['filtered']['amount']);
        // ومجموع اليوم يبقى مجموع اليوم كله، لا مجموع الترشيح.
        $this->assertSame('1700.00', $summary['today']);
    }

    public function test_the_summary_ranks_the_heaviest_categories(): void
    {
        $this->actingAs($this->userWith(['expenses.create', 'expenses.view']));
        $fuel = ExpenseCategory::query()->create(['name' => 'مواصلات']);

        $this->record('1000', '2026-05-20');
        $this->record('4000', '2026-05-20', $fuel->id);

        $rows = $this->getJson('/api/expenses')->assertOk()->json('summary.by_category');

        $this->assertSame('مواصلات', $rows[0]['name']);
        $this->assertSame('4000.00', $rows[0]['amount']);
        $this->assertSame('إيجار المحل', $rows[1]['name']);
    }

    public function test_the_search_matches_the_number_the_payee_and_the_note(): void
    {
        $this->actingAs($this->userWith(['expenses.create', 'expenses.view']));

        $this->postJson('/api/expenses', [
            'expense_category_id' => $this->rent->id,
            'amount' => '1000',
            'payee' => 'شركة الكهرباء',
        ])->assertCreated();
        $this->record('2000');

        $this->assertCount(1, $this->getJson('/api/expenses?search=الكهرباء')->json('data'));
        $this->assertCount(1, $this->getJson('/api/expenses?search=EXP-000002')->json('data'));
    }

    // ------------------------------------------------------------ categories

    public function test_a_category_in_use_cannot_be_deleted_but_can_be_disabled(): void
    {
        $this->actingAs($this->userWith(['expenses.create', 'expenses.edit', 'expenses.view']));
        $this->record('1000');

        $this->deleteJson("/api/expense-categories/{$this->rent->id}")->assertStatus(422);

        $this->putJson("/api/expense-categories/{$this->rent->id}", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('expense_categories', ['id' => $this->rent->id, 'is_active' => false]);
    }

    public function test_an_unused_category_can_be_deleted(): void
    {
        $spare = ExpenseCategory::query()->create(['name' => 'باب زائد']);
        $this->actingAs($this->userWith(['expenses.edit']));

        $this->deleteJson("/api/expense-categories/{$spare->id}")->assertOk();
        $this->assertDatabaseMissing('expense_categories', ['id' => $spare->id]);
    }

    public function test_category_names_do_not_repeat(): void
    {
        $this->actingAs($this->userWith(['expenses.create']));

        $this->postJson('/api/expense-categories', ['name' => 'إيجار المحل'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_categories_are_listed_most_used_first(): void
    {
        $this->actingAs($this->userWith(['expenses.create', 'expenses.view']));
        $fuel = ExpenseCategory::query()->create(['name' => 'مواصلات']);

        $this->record('100', '2026-05-20', $fuel->id);
        $this->record('100', '2026-05-20', $fuel->id);
        $this->record('100');

        $names = collect($this->getJson('/api/expense-categories')->assertOk()->json('data'))->pluck('name');

        $this->assertSame('مواصلات', $names->first());
    }

    // ------------------------------------------------------------- dashboard

    public function test_the_dashboard_counts_expenses_in_the_period(): void
    {
        $this->actingAs($this->userWith(['expenses.create', 'expenses.cancel', 'dashboard.view']));

        $this->record('1000', '2026-05-20');
        $cancelled = $this->record('5000', '2026-05-20')->json('data.id');
        $this->postJson("/api/expenses/{$cancelled}/cancel", ['reason' => 'مبلغ خاطئ'])->assertOk();

        $data = $this->getJson('/api/dashboard?from=2026-05-01&to=2026-05-31')->assertOk()->json('data');

        $this->assertSame('1000.00', $data['kpis']['expenses_period']['amount']);
        $this->assertSame(1, $data['kpis']['expenses_period']['count']);
        $this->assertSame('expense', $data['recent_documents'][0]['kind']);
        $this->assertSame('out', $data['recent_documents'][0]['direction']);
    }

    // ------------------------------------------------------------- factories

    protected function record(string $amount, ?string $date = null, ?int $categoryId = null)
    {
        return $this->postJson('/api/expenses', [
            'expense_category_id' => $categoryId ?? $this->rent->id,
            'amount' => $amount,
            'expense_date' => $date,
        ])->assertCreated();
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
