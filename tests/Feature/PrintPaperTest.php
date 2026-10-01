<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\PaymentType;
use App\Enums\RepairStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Repair;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintPaperTest extends TestCase
{
    use RefreshDatabase;

    /** Points per millimetre, as PDF page boxes are measured. */
    protected const PT = 72 / 25.4;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->userWith(['sales.view', 'payments.create', 'statements.view', 'repairs.view']));

        $this->customer = Customer::query()->create([
            'name' => 'مؤسسة الأمل', 'phone' => '0933 111 222', 'opening_balance' => '0.00', 'is_active' => true,
        ]);
    }

    public function test_the_migration_seeds_a4_defaults_so_existing_prints_are_unchanged(): void
    {
        foreach (['invoice', 'payment', 'repair', 'statement'] as $kind) {
            $this->assertSame('a4', Setting::get("print_paper_{$kind}"));
        }

        [$width] = $this->pageSize($this->get("/api/pdf/sales/{$this->invoice()->id}")->assertOk()->getContent());

        $this->assertEqualsWithDelta(210, $width, 0.5);
    }

    public function test_a_document_prints_on_the_paper_asked_for(): void
    {
        $invoice = $this->invoice();

        [$width, $height] = $this->pageSize(
            $this->get("/api/pdf/sales/{$invoice->id}?paper=a5")->assertOk()->getContent()
        );

        $this->assertEqualsWithDelta(148, $width, 0.5);
        $this->assertEqualsWithDelta(210, $height, 0.5);
    }

    public function test_the_shop_default_applies_when_no_paper_is_asked_for(): void
    {
        Setting::query()->where('key', 'print_paper_payment')->update(['value' => '80mm']);

        [$width] = $this->pageSize(
            $this->get("/api/pdf/payments/{$this->payment()->id}")->assertOk()->getContent()
        );

        $this->assertEqualsWithDelta(80, $width, 0.5);
    }

    public function test_a_thermal_receipt_is_cut_to_its_content_rather_than_a_full_page(): void
    {
        $repair = $this->repair();

        foreach (['80mm' => 80, '58mm' => 58] as $paper => $mm) {
            [$width, $height] = $this->pageSize(
                $this->get("/api/pdf/repairs/{$repair->id}?paper={$paper}")->assertOk()->getContent()
            );

            $this->assertEqualsWithDelta($mm, $width, 0.5);
            $this->assertGreaterThanOrEqual(60, $height);
            $this->assertLessThan(297, $height, "A {$paper} slip should not feed a full A4 length of paper.");
        }
    }

    public function test_every_document_renders_on_every_paper_it_supports(): void
    {
        Setting::query()->where('key', 'print_footer_note')->update(['value' => "شكرًا لزيارتكم\nالبضاعة المباعة لا ترد"]);
        Setting::query()->where('key', 'print_repair_terms')->update(['value' => 'لا يتحمل المركز مسؤولية الأجهزة بعد 30 يومًا.']);

        $urls = [
            "/api/pdf/sales/{$this->invoice()->id}",
            "/api/pdf/payments/{$this->payment()->id}",
            "/api/pdf/repairs/{$this->repair()->id}",
        ];

        foreach ($urls as $url) {
            foreach (['a4', 'a5', '80mm', '58mm'] as $paper) {
                $this->assertStringStartsWith(
                    '%PDF-',
                    $this->get("{$url}?paper={$paper}")->assertOk()->getContent(),
                    "{$url} on {$paper}",
                );
            }
        }
    }

    public function test_a_statement_cannot_be_printed_on_a_thermal_roll(): void
    {
        $this->getJson("/api/pdf/customers/{$this->customer->id}/statement?paper=80mm")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paper');

        $this->get("/api/pdf/customers/{$this->customer->id}/statement?paper=a5")->assertOk();
    }

    public function test_an_unknown_paper_is_refused(): void
    {
        $this->getJson("/api/pdf/sales/{$this->invoice()->id}?paper=letter")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paper');
    }

    public function test_a_stored_default_the_document_cannot_use_falls_back_to_a4(): void
    {
        // Written behind the settings screen's back; the PDF must still print.
        Setting::query()->where('key', 'print_paper_statement')->update(['value' => '80mm']);

        [$width] = $this->pageSize(
            $this->get("/api/pdf/customers/{$this->customer->id}/statement")->assertOk()->getContent()
        );

        $this->assertEqualsWithDelta(210, $width, 0.5);
    }

    public function test_the_print_config_lists_papers_and_the_shop_defaults(): void
    {
        Setting::query()->where('key', 'print_paper_repair')->update(['value' => '58mm']);

        // Any signed-in user may read it: the print buttons live on every screen.
        $this->actingAs($this->userWith([]));

        $data = $this->getJson('/api/print/config')->assertOk()->json('data');

        $this->assertSame(['a4', 'a5', '80mm', '58mm'], array_column($data['papers'], 'value'));
        $this->assertSame(['a4', 'a5'], $data['supported']['statement']);
        $this->assertSame('58mm', $data['defaults']['repair']);
        $this->assertSame('a4', $data['defaults']['invoice']);
    }

    public function test_a_test_page_prints_on_any_paper_without_document_permissions(): void
    {
        $this->actingAs($this->userWith([]));

        foreach (['a4', 'a5', '80mm', '58mm'] as $paper) {
            $response = $this->get("/api/pdf/sample?paper={$paper}")->assertOk();

            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->assertStringContainsString("print-test-{$paper}.pdf", $response->headers->get('Content-Disposition'));
        }
    }

    public function test_a_guest_cannot_read_the_print_config(): void
    {
        auth()->guard('web')->logout();

        $this->getJson('/api/print/config')->assertUnauthorized();
    }

    // ---------------------------------------------------------------- helpers

    /**
     * The first page's size in millimetres, read off its MediaBox.
     *
     * @return array{0: float, 1: float}
     */
    protected function pageSize(string $pdf): array
    {
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf);
        preg_match('/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $m);

        return [(float) $m[1] / self::PT, (float) $m[2] / self::PT];
    }

    protected function invoice(): SalesInvoice
    {
        return SalesInvoice::query()->create([
            'invoice_no' => 'SL-0001',
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-05-02',
            'status' => DocumentStatus::POSTED,
            'subtotal' => '1000.00', 'total' => '1000.00', 'remaining' => '1000.00',
            'created_by' => auth()->id(),
        ]);
    }

    protected function payment(): Payment
    {
        return Payment::query()->create([
            'payment_type' => PaymentType::CUSTOMER,
            'customer_id' => $this->customer->id,
            'amount' => '8500.00',
            'payment_method' => 'CASH',
            'reference_no' => 'PAY-0001',
            'payment_date' => '2026-05-05',
            'status' => DocumentStatus::POSTED,
            'recorded_by' => auth()->id(),
        ]);
    }

    protected function repair(): Repair
    {
        return Repair::query()->create([
            'customer_id' => $this->customer->id,
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

        $role = Role::query()->create(['name' => 'دور '.$user->id, 'slug' => 'role-'.$user->id]);

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
