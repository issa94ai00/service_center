<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // 5. Default feature settings in settings table
        $defaultFeatures = [
            'feature_repairs' => '1',
            'feature_technicians' => '1',
            'feature_purchases' => '1',
            'feature_expenses' => '1',
            'feature_reports' => '1',
            'feature_services' => '1',
            'feature_inventory' => '1',
            'feature_sales' => '1',
        ];

        foreach ($defaultFeatures as $key => $defaultVal) {
            Setting::firstOrCreate(
                ['key' => $key],
                [
                    'value' => $defaultVal,
                    'group' => 'features',
                    'type' => 'bool',
                ]
            );
        }

        if (app()->environment('testing')) {
            return;
        }

        // 1. Ensure 'features.manage' permission exists
        $featurePermission = Permission::firstOrCreate(
            ['slug' => 'features.manage'],
            [
                'name' => 'إدارة وتفعيل ميزات النظام',
                'group' => 'access',
            ]
        );

        // 2. Ensure 'master' role exists
        $masterRole = Role::firstOrCreate(
            ['slug' => 'master'],
            [
                'name' => 'المدير العام',
                'description' => 'صلاحيات عليا للتحكم الكامل وإدارة ميزات وإعدادات النظام',
            ]
        );

        // Ensure admin role exists
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'مدير النظام',
                'description' => 'إدارة النظام والعمليات',
            ]
        );

        // 3. Attach all permissions to 'master' role
        $allPermissionIds = Permission::pluck('id')->all();
        $masterRole->permissions()->sync($allPermissionIds);

        // 4. Create or update master user
        $masterUser = User::firstOrNew(['email' => 'master@damatech.sy']);
        $masterUser->name = 'المدير العام (Master)';
        $masterUser->password = Hash::make('master123');
        $masterUser->is_active = true;
        $masterUser->locale = 'ar';
        $masterUser->save();

        // Attach master role (and admin role)
        $masterUser->roles()->syncWithoutDetaching([$masterRole->id, $adminRole->id]);


    }

    public function down(): void
    {
        // Safe down
    }
};
