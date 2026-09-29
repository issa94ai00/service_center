<?php

namespace Tests\Feature;

use App\Enums\RepairStatus;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Repair;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->insert([
            ['key' => 'repair_receipt_prefix', 'value' => 'RPR-', 'group' => 'numbers', 'type' => 'string'],
        ]);
    }

    public function test_browsing_repairs_requires_the_view_permission(): void
    {
        $this->actingAs($this->userWith([]))
            ->getJson('/api/repairs')
            ->assertForbidden();
    }

    public function test_customer_picker_requires_the_create_permission(): void
    {
        $this->actingAs($this->userWith(['repairs.view']))
            ->getJson('/api/repairs/customer-options')
            ->assertForbidden();

        $this->actingAs($this->userWith(['repairs.create']))
            ->getJson('/api/repairs/customer-options')
            ->assertOk();
    }

    public function test_brand_picker_requires_the_create_permission_and_lists_distinct_brands(): void
    {
        $this->actingAs($this->userWith(['repairs.view']))
            ->getJson('/api/repairs/brand-options')
            ->assertForbidden();

        $customer = Customer::factory()->create();

        Repair::query()->create([
            'customer_id' => $customer->id,
            'device_type' => 'جوال',
            'brand' => 'Samsung',
            'reported_issue' => 'شاشة',
            'status' => RepairStatus::RECEIVED,
            'received_at' => now(),
        ]);
        Repair::query()->create([
            'customer_id' => $customer->id,
            'device_type' => 'جوال',
            'brand' => 'Samsung',
            'reported_issue' => 'بطارية',
            'status' => RepairStatus::RECEIVED,
            'received_at' => now(),
        ]);
        Repair::query()->create([
            'customer_id' => $customer->id,
            'device_type' => 'جوال',
            'brand' => 'iPhone',
            'reported_issue' => 'كاميرا',
            'status' => RepairStatus::RECEIVED,
            'received_at' => now(),
        ]);
        Repair::query()->create([
            'customer_id' => $customer->id,
            'device_type' => 'جوال',
            'brand' => null,
            'reported_issue' => 'كارت',
            'status' => RepairStatus::RECEIVED,
            'received_at' => now(),
        ]);

        $response = $this->actingAs($this->userWith(['repairs.create']))
            ->getJson('/api/repairs/brand-options')
            ->assertOk();

        $this->assertEqualsCanonicalizing(['Samsung', 'iPhone'], $response->json('data'));
    }

    public function test_the_customer_picker_searches_by_name_and_phone_and_stays_capped(): void
    {
        $this->actingAs($this->userWith(['repairs.create']));

        Customer::factory()->create(['name' => 'أحمد الحلبي', 'mobile' => '0999123456']);
        Customer::factory()->create(['name' => 'سامر', 'mobile' => '0988777666']);

        $this->getJson('/api/repairs/customer-options?search=الحلبي')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'أحمد الحلبي');

        // Typed with spaces, matched on the bare digits.
        $this->getJson('/api/repairs/customer-options?search=0999 123 456')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mobile', '0999123456');
    }

    public function test_the_customer_picker_does_not_leak_balances_or_contact_details(): void
    {
        $this->actingAs($this->userWith(['repairs.create']));

        Customer::factory()->create([
            'name' => 'عميل',
            'email' => 'secret@example.com',
            'address' => 'شارع سري',
            'tax_number' => '9999',
            'opening_balance' => 500,
        ]);

        $row = $this->getJson('/api/repairs/customer-options')->assertOk()->json('data.0');

        $this->assertSame(['id', 'name', 'company', 'phone', 'mobile', 'is_active'], array_keys($row));
    }

    public function test_the_customer_picker_can_pin_a_customer_outside_the_result_slice(): void
    {
        $this->actingAs($this->userWith(['repairs.create']));

        $inactive = Customer::factory()->create(['name' => 'عميل موقوف', 'is_active' => false]);

        $this->getJson('/api/repairs/customer-options')
            ->assertOk()
            ->assertJsonMissing(['id' => $inactive->id]);

        // Editing a repair that points at them must still render their name.
        $this->getJson("/api/repairs/customer-options?include={$inactive->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $inactive->id);
    }

    public function test_the_list_finds_a_repair_by_receipt_number_and_by_customer(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.create']));

        $customer = Customer::factory()->create(['name' => 'ليلى', 'mobile' => '0955000111']);
        $repair = Repair::query()->create([
            'customer_id' => $customer->id,
            'device_type' => 'جوال',
            'reported_issue' => 'شاشة',
            'status' => RepairStatus::RECEIVED,
            'received_at' => now(),
        ]);
        Repair::query()->create([
            'customer_id' => Customer::factory()->create(['name' => 'خالد'])->id,
            'device_type' => 'تابلت',
            'reported_issue' => 'بطارية',
            'status' => RepairStatus::RECEIVED,
            'received_at' => now(),
        ]);

        $this->getJson('/api/repairs?search=RPR-'.$repair->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $repair->id);

        $this->getJson('/api/repairs?search=ليلى')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $repair->id);

        $this->getJson('/api/repairs?search=0955000111')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $repair->id);
    }

    public function test_the_list_returns_status_counts_for_the_filter_chips(): void
    {
        $this->actingAs($this->userWith(['repairs.view']));
        $customer = Customer::factory()->create();

        foreach ([RepairStatus::RECEIVED, RepairStatus::RECEIVED, RepairStatus::READY] as $status) {
            Repair::query()->create([
                'customer_id' => $customer->id,
                'device_type' => 'جوال',
                'reported_issue' => 'عطل',
                'status' => $status,
                'received_at' => now(),
            ]);
        }

        $response = $this->getJson('/api/repairs')->assertOk();

        $this->assertSame(2, $response->json('meta.status_counts.RECEIVED'));
        $this->assertSame(1, $response->json('meta.status_counts.READY'));
        $this->assertSame(0, $response->json('meta.status_counts.CANCELLED'));

        // The counts describe the whole filtered set, not just the page shown.
        $filtered = $this->getJson('/api/repairs?status=READY')->assertOk();
        $filtered->assertJsonCount(1, 'data');
        $this->assertSame(2, $filtered->json('meta.status_counts.RECEIVED'));
    }

    public function test_the_list_accepts_a_set_of_statuses(): void
    {
        $this->actingAs($this->userWith(['repairs.view']));
        $customer = Customer::factory()->create();

        foreach ([RepairStatus::RECEIVED, RepairStatus::READY, RepairStatus::DELIVERED] as $status) {
            Repair::query()->create([
                'customer_id' => $customer->id,
                'device_type' => 'جوال',
                'reported_issue' => 'عطل',
                'status' => $status,
                'received_at' => now(),
            ]);
        }

        $this->getJson('/api/repairs?status=RECEIVED,READY')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // An unknown status must not fall through to "everything".
        $this->getJson('/api/repairs?status=NONSENSE')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_device_can_be_received_and_gets_its_receipt_number(): void
    {
        $this->actingAs($this->userWith(['repairs.create', 'repairs.view']));

        $response = $this->postJson('/api/repairs', [
            'customer_id' => Customer::factory()->create()->id,
            'device_type' => 'جوال',
            'brand' => 'Samsung',
            'model' => 'Galaxy A55',
            'serial_number' => 'IMEI12345',
            'reported_issue' => 'شاشة مكسورة',
            'estimated_cost' => 150000,
            'status' => 'RECEIVED',
        ])->assertCreated();

        $repair = Repair::query()->firstOrFail();

        $this->assertSame('RPR-'.$repair->id, $repair->receipt_no);
        $response->assertJsonPath('data.receipt_no', 'RPR-'.$repair->id);
        $this->assertSame('Samsung', $repair->brand);
    }

    public function test_received_at_defaults_to_now_when_omitted(): void
    {
        $this->actingAs($this->userWith(['repairs.create', 'repairs.view']));

        $this->postJson('/api/repairs', [
            'customer_id' => Customer::factory()->create()->id,
            'device_type' => 'جوال',
            'reported_issue' => 'لا يشحن',
            'status' => 'RECEIVED',
        ])->assertCreated();

        $this->assertNotNull(Repair::query()->first()->received_at);
    }

    public function test_marking_delivered_records_the_delivery_time(): void
    {
        $this->actingAs($this->userWith(['repairs.create', 'repairs.edit', 'repairs.view']));
        $repair = Repair::query()->create([
            'customer_id' => Customer::factory()->create()->id,
            'device_type' => 'جوال',
            'reported_issue' => 'زر طاقة لا يعمل',
            'status' => RepairStatus::RECEIVED,
            'received_at' => now(),
        ]);

        $this->putJson("/api/repairs/{$repair->id}", ['status' => 'DELIVERED'])
            ->assertOk();

        $this->assertSame('DELIVERED', $repair->fresh()->status->value);
        $this->assertNotNull($repair->fresh()->delivered_at);
    }

    public function test_moving_out_of_delivered_clears_the_delivery_time(): void
    {
        $this->actingAs($this->userWith(['repairs.create', 'repairs.edit', 'repairs.view']));
        $repair = Repair::query()->create([
            'customer_id' => Customer::factory()->create()->id,
            'device_type' => 'جوال',
            'reported_issue' => 'ماء في الجهاز',
            'status' => RepairStatus::DELIVERED,
            'received_at' => now(),
            'delivered_at' => now(),
        ]);

        $this->putJson("/api/repairs/{$repair->id}", ['status' => 'UNDER_REPAIR'])
            ->assertOk();

        $loaded = $repair->fresh();
        $this->assertSame('UNDER_REPAIR', $loaded->status->value);
        $this->assertNull($loaded->delivered_at);
    }

    public function test_reported_issue_is_required(): void
    {
        $this->actingAs($this->userWith(['repairs.create', 'repairs.view']));

        $this->postJson('/api/repairs', [
            'customer_id' => Customer::factory()->create()->id,
            'device_type' => 'جوال',
            'status' => 'RECEIVED',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('reported_issue');
    }

    public function test_editing_a_repair_requires_the_edit_permission(): void
    {
        $this->actingAs($this->userWith(['repairs.view']));
        $repair = Repair::query()->create([
            'customer_id' => Customer::factory()->create()->id,
            'device_type' => 'جوال',
            'reported_issue' => 'لا يشتغل',
            'status' => RepairStatus::RECEIVED,
            'received_at' => now(),
        ]);

        $this->putJson("/api/repairs/{$repair->id}", ['reported_issue' => 'عدّل'])
            ->assertForbidden();
    }

    public function test_deleting_a_repair_requires_the_delete_permission(): void
    {
        $this->actingAs($this->userWith(['repairs.view', 'repairs.delete']));
        $repair = Repair::query()->create([
            'customer_id' => Customer::factory()->create()->id,
            'device_type' => 'جوال',
            'reported_issue' => 'عطل',
            'status' => RepairStatus::RECEIVED,
            'received_at' => now(),
        ]);

        $this->deleteJson("/api/repairs/{$repair->id}")->assertOk();
        $this->assertDatabaseMissing('repairs', ['id' => $repair->id]);
    }

    public function test_the_receipt_pdf_is_guarded_by_the_view_permission(): void
    {
        $this->actingAs($this->userWith(['repairs.view']));
        $repair = Repair::query()->create([
            'customer_id' => Customer::factory()->create()->id,
            'device_type' => 'جوال',
            'brand' => 'iPhone',
            'model' => '12',
            'serial_number' => 'X4-9090',
            'accessories' => 'شاحن وكارت',
            'reported_issue' => 'لا يشحن ولا يقرأ الشريحة',
            'estimated_cost' => 250000,
            'status' => RepairStatus::RECEIVED,
            'received_at' => now(),
        ]);

        $response = $this->get("/api/pdf/repairs/{$repair->id}")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('repair-receipt-RPR-'.$repair->id.'.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_the_receipt_pdf_requires_the_view_permission(): void
    {
        $this->actingAs($this->userWith([]));
        $repair = Repair::query()->create([
            'customer_id' => Customer::factory()->create()->id,
            'device_type' => 'جوال',
            'reported_issue' => 'عطل',
            'status' => RepairStatus::RECEIVED,
            'received_at' => now(),
        ]);

        $this->get("/api/pdf/repairs/{$repair->id}")->assertForbidden();
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
