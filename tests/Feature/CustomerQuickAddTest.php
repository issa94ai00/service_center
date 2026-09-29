<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الإضافة السريعة للعميل من شاشات المحاسبة (شاشة البيع تحديداً): العميل يُنشأ
 * من نافذة صغيرة داخل النموذج، ثم يُختار فوراً دون مغادرة الفاتورة.
 *
 * ما يحرسه هذا الملف هو *عقد* الواجهة: الشكل الذي يقرؤه المنتقي من الرد. كسر
 * هذا العقد لا يظهر كخطأ واضح، بل كرسالة «تعذّر حفظ العميل» فوق عميل تم حفظه
 * فعلاً — فيعيد الموظف المحاولة ويتكرّر السجل.
 */
class CustomerQuickAddTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The payload PartySelect's quick-add form actually posts: a name plus
     * three optional fields, each sent as null rather than omitted when empty.
     */
    private function quickAddPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'أبو خالد',
            'mobile' => '0912345678',
            'phone' => null,
            'company' => null,
        ], $overrides);
    }

    public function test_it_creates_a_customer_and_returns_the_record_one_level_deep(): void
    {
        $this->actingAs($this->userWith(['customers.create']));

        $response = $this->postJson('/api/customers', $this->quickAddPayload([
            'company' => 'محل النور',
        ]))->assertCreated();

        // The envelope's depth is the contract, not an incidental detail. The
        // picker reads the created customer from `data` and refuses to select a
        // record with no id; one extra level of nesting here is invisible to
        // the backend and surfaces at the counter as a failed save.
        $response
            ->assertJsonPath('data.name', 'أبو خالد')
            ->assertJsonPath('data.mobile', '0912345678')
            ->assertJsonPath('data.company', 'محل النور')
            ->assertJsonMissingPath('data.data');

        $this->assertIsInt($response->json('data.id'));

        $this->assertDatabaseHas('customers', [
            'id' => $response->json('data.id'),
            'name' => 'أبو خالد',
            'mobile' => '0912345678',
            'company' => 'محل النور',
        ]);
    }

    public function test_a_quick_added_customer_is_immediately_selectable_on_the_sales_form(): void
    {
        $this->actingAs($this->userWith(['customers.create', 'sales.create']));

        $id = $this->postJson('/api/customers', $this->quickAddPayload())
            ->assertCreated()
            ->json('data.id');

        // The picker's own search only lists active parties, and the store
        // request carries no `is_active`. If the column ever stops defaulting
        // to true, the customer would be created and then vanish from the very
        // field that created them.
        $this->getJson('/api/sales/customer-options?search=خالد')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.name', 'أبو خالد');
    }

    public function test_it_accepts_a_walk_in_with_no_phone_number_at_all(): void
    {
        $this->actingAs($this->userWith(['customers.create']));

        // The form warns about this case but must never block it — a customer
        // standing at the counter has to be recordable in one pass.
        $this->postJson('/api/customers', $this->quickAddPayload([
            'mobile' => null,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.mobile', null)
            ->assertJsonPath('data.phone', null);

        $this->assertDatabaseHas('customers', ['name' => 'أبو خالد', 'mobile' => null]);
    }

    public function test_it_refuses_a_customer_with_no_name(): void
    {
        $this->actingAs($this->userWith(['customers.create']));

        // `errors` keyed by field is the shape apiFieldErrors() reads to paint
        // the offending input red.
        $this->postJson('/api/customers', $this->quickAddPayload(['name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_two_customers_may_share_one_phone_number(): void
    {
        $this->actingAs($this->userWith(['customers.create']));

        Customer::factory()->create(['name' => 'أبو سامر', 'mobile' => '0912345678']);

        // The form shows the match and makes saving over it take a second,
        // deliberate click — but two people really do share a shop line, so the
        // endpoint must not treat it as an error.
        $this->postJson('/api/customers', $this->quickAddPayload())->assertCreated();

        $this->assertSame(2, Customer::query()->where('mobile', '0912345678')->count());
    }

    public function test_quick_add_is_refused_without_the_create_permission(): void
    {
        $this->actingAs($this->userWith([]));

        $this->postJson('/api/customers', $this->quickAddPayload())->assertForbidden();

        $this->assertDatabaseCount('customers', 0);
    }

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
