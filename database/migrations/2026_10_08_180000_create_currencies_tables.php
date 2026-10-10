<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Managed currencies on top of the USD base.
 *
 * The books stay in USD: every ledger line, invoice and report keeps its one
 * amount column. A receipt, payment or expense handled in another currency
 * additionally records what actually changed hands (currency_amount) and the
 * rate it was converted at, so the USD figure can always be traced back.
 * Existing rows need no backfill: they were all USD, which the defaults say.
 *
 * Rates are "units of the currency per 1 USD" (e.g. SYP 13000), the way a
 * cashier quotes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name', 50);
            $table->string('symbol', 10);
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_invoices')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->decimal('previous_rate', 18, 6)->nullable();
            $table->decimal('exchange_rate', 18, 6);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });

        DB::table('currencies')->insert([
            'code' => 'USD',
            'name' => 'دولار أمريكي',
            'symbol' => '$',
            'exchange_rate' => 1,
            'decimals' => 2,
            'is_base' => true,
            'is_active' => true,
            'show_on_invoices' => false,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['payments', 'expenses'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->char('currency_code', 3)->default('USD')->after('amount');
                $t->decimal('exchange_rate', 18, 6)->default(1)->after('currency_code');
                // Null for USD rows: the amount column already is what changed hands.
                $t->decimal('currency_amount', 18, 2)->nullable()->after('exchange_rate');
            });
        }

        DB::table('permissions')->updateOrInsert(
            ['slug' => 'currencies.manage'],
            ['name' => 'إدارة العملات وأسعار الصرف', 'group' => 'access', 'created_at' => now(), 'updated_at' => now()],
        );

        // Whoever could already edit the settings (where the currency used to
        // live) can manage currencies; Master holds every permission anyway.
        $permissionId = DB::table('permissions')->where('slug', 'currencies.manage')->value('id');
        $settingsId = DB::table('permissions')->where('slug', 'settings.manage')->value('id');

        $roleIds = DB::table('role_permissions')->where('permission_id', $settingsId)->pluck('role_id')
            ->merge(DB::table('roles')->where('slug', 'master')->pluck('id'))
            ->unique();

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        foreach (['payments', 'expenses'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['currency_code', 'exchange_rate', 'currency_amount']);
            });
        }

        $permissionId = DB::table('permissions')->where('slug', 'currencies.manage')->value('id');
        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();

        Schema::dropIfExists('currency_rates');
        Schema::dropIfExists('currencies');
    }
};
