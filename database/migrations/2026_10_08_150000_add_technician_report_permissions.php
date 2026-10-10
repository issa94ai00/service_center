<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits the two technician reports off reports.view. Every role that could read the
 * reports keeps both; Master holds every permission anyway.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'reports.technicians' => 'تقرير محاسبة خبراء الصيانة',
        'reports.technician_repairs' => 'تقرير عمولات الخبراء لكل بلاغ',
    ];

    public function up(): void
    {
        $roleIds = DB::table('role_permissions')
            ->where('permission_id', DB::table('permissions')->where('slug', 'reports.view')->value('id'))
            ->pluck('role_id')
            ->merge(DB::table('roles')->where('slug', 'master')->pluck('id'))
            ->unique();

        foreach (self::PERMISSIONS as $slug => $name) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'group' => 'reports', 'created_at' => now(), 'updated_at' => now()],
            );

            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');

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
