<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Returns get their own permission rather than riding on cancel: taking back
 * one damaged screen is a counter job, voiding a whole invoice is not. Every
 * role that could cancel an invoice may take a return on it; Master holds
 * every permission anyway.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'sales.return' => ['name' => 'تسجيل مرتجع بيع', 'group' => 'sales', 'from' => 'sales.cancel'],
        'purchases.return' => ['name' => 'تسجيل مرتجع شراء', 'group' => 'purchases', 'from' => 'purchases.cancel'],
    ];

    public function up(): void
    {
        $masters = DB::table('roles')->where('slug', 'master')->pluck('id');

        foreach (self::PERMISSIONS as $slug => $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $permission['name'], 'group' => $permission['group'], 'created_at' => now(), 'updated_at' => now()],
            );

            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');

            $roleIds = DB::table('role_permissions')
                ->where('permission_id', DB::table('permissions')->where('slug', $permission['from'])->value('id'))
                ->pluck('role_id')
                ->merge($masters)
                ->unique();

            foreach ($roleIds as $roleId) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
