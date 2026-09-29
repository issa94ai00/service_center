<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repairs', function (Blueprint $table) {
            // The price actually agreed with the customer once the fault is
            // known. estimated_cost stays what was quoted at the counter, so a
            // receipt printed on day one keeps telling the truth.
            $table->decimal('final_cost', 15, 2)->nullable()->after('estimated_cost');
            // The invoice this repair was settled with, once it is handed over.
            $table->foreignId('sales_invoice_id')->nullable()->after('final_cost')
                ->constrained('sales_invoices')->nullOnDelete();
            $table->dateTime('settled_at')->nullable()->after('delivered_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            // Deposits taken at intake belong to a repair, not to an invoice
            // that does not exist yet. This is the only link between the two.
            $table->foreignId('repair_id')->nullable()->after('supplier_id')
                ->constrained('repairs')->nullOnDelete();
        });

        // Taking money and settling a repair is a distinct capability: it posts
        // to the customer ledger and issues an invoice, which is more than the
        // counter clerk who only registers devices should be able to do.
        $permissionId = DB::table('permissions')->where('slug', 'repairs.settle')->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'repairs.settle',
                'slug' => 'repairs.settle',
                'group' => 'repairs',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Grant it to the roles that already hold the payment permissions it
        // stands in for, so an existing install does not have to be reseeded.
        $roleIds = DB::table('roles')->whereIn('slug', ['admin', 'accountant'])->pluck('id');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists();

            if (! $exists) {
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'repairs.settle')->value('id');
        if ($permissionId) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('repair_id');
        });

        Schema::table('repairs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_invoice_id');
            $table->dropColumn(['final_cost', 'settled_at']);
        });
    }
};
