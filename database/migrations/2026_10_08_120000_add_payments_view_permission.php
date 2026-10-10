<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits viewing receipts/payments off payments.create. Every role that could create
 * payments (and so see them) keeps seeing them; Master holds every permission anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'payments.view'],
            ['name' => 'عرض سندات القبض والدفع', 'group' => 'payments', 'created_at' => now(), 'updated_at' => now()],
        );

        $viewId = DB::table('permissions')->where('slug', 'payments.view')->value('id');
        $createId = DB::table('permissions')->where('slug', 'payments.create')->value('id');

        $roleIds = DB::table('role_permissions')->where('permission_id', $createId)->pluck('role_id')
            ->merge(DB::table('roles')->where('slug', 'master')->pluck('id'))
            ->unique();

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $viewId]);
        }
    }

    public function down(): void
    {
        $viewId = DB::table('permissions')->where('slug', 'payments.view')->value('id');

        DB::table('role_permissions')->where('permission_id', $viewId)->delete();
        DB::table('permissions')->where('id', $viewId)->delete();
    }
};
