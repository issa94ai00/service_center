<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->units() as $unit) {
            DB::table('service_units')->updateOrInsert(
                ['name_ar' => $unit['name_ar']],
                $unit
            );
        }

        foreach ($this->settings() as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting + ['updated_at' => now()]
            );
        }

        foreach ($this->permissions() as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission[0]],
                [
                    'name' => $permission[1],
                    'group' => $permission[2],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        foreach ($this->rolesSlugs() as $slug => $name) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'description' => null, 'updated_at' => now()]
            );
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        DB::table('role_permissions')
            ->where('role_id', $adminRoleId)
            ->delete();
        $adminPerms = DB::table('permissions')
            ->whereNotIn('slug', ['features.manage', 'roles.manage'])
            ->pluck('id')
            ->map(fn ($pid) => ['role_id' => $adminRoleId, 'permission_id' => $pid])
            ->all();
        DB::table('role_permissions')->insert($adminPerms);

        $masterRoleId = DB::table('roles')->where('slug', 'master')->value('id');
        if ($masterRoleId) {
            DB::table('role_permissions')->where('role_id', $masterRoleId)->delete();
            DB::table('role_permissions')->insert(
                DB::table('permissions')->pluck('id')->map(
                    fn ($pid) => ['role_id' => $masterRoleId, 'permission_id' => $pid]
                )->all()
            );
        }

        $excluded = ['users.manage', 'roles.manage', 'settings.manage', 'currencies.manage'];
        $accountantPerms = DB::table('permissions')
            ->whereNotIn('slug', $excluded)
            ->pluck('id')
            ->all();
        $accountantRoleId = DB::table('roles')->where('slug', 'accountant')->value('id');
        DB::table('role_permissions')
            ->where('role_id', $accountantRoleId)
            ->delete();
        DB::table('role_permissions')->insert(
            array_map(fn ($pid) => ['role_id' => $accountantRoleId, 'permission_id' => $pid], $accountantPerms)
        );
    }

    private function units(): array
    {
        return [
            ['name_ar' => 'متر', 'name_en' => 'Meter', 'symbol' => 'م', 'category' => 'LENGTH', 'is_measurable' => 1],
            ['name_ar' => 'متر مربع', 'name_en' => 'Square Meter', 'symbol' => 'م²', 'category' => 'AREA', 'is_measurable' => 1],
            ['name_ar' => 'متر مكعب', 'name_en' => 'Cubic Meter', 'symbol' => 'م³', 'category' => 'VOLUME', 'is_measurable' => 1],
            ['name_ar' => 'قطعة', 'name_en' => 'Piece', 'symbol' => 'قطعة', 'category' => 'COUNT', 'is_measurable' => 1],
            ['name_ar' => 'جهاز', 'name_en' => 'Device', 'symbol' => 'جهاز', 'category' => 'COUNT', 'is_measurable' => 1],
            ['name_ar' => 'ساعة', 'name_en' => 'Hour', 'symbol' => 'س', 'category' => 'TIME', 'is_measurable' => 1],
            ['name_ar' => 'يوم', 'name_en' => 'Day', 'symbol' => 'يوم', 'category' => 'TIME', 'is_measurable' => 1],
        ];
    }

    private function settings(): array
    {
        return [
            ['key' => 'company_name', 'value' => 'مركز الهواتف والإكسسوارات', 'group' => 'general', 'type' => 'string'],
            ['key' => 'currency_code', 'value' => 'USD', 'group' => 'general', 'type' => 'string'],
            ['key' => 'currency_symbol', 'value' => '$', 'group' => 'general', 'type' => 'string'],
            ['key' => 'currency_name', 'value' => 'دولار', 'group' => 'general', 'type' => 'string'],
            ['key' => 'company_address', 'value' => '', 'group' => 'general', 'type' => 'string'],
            ['key' => 'company_phone', 'value' => '', 'group' => 'general', 'type' => 'string'],
            ['key' => 'company_email', 'value' => '', 'group' => 'general', 'type' => 'string'],
            ['key' => 'company_tax_number', 'value' => '', 'group' => 'general', 'type' => 'string'],
            ['key' => 'allow_overpayment', 'value' => '0', 'group' => 'financial', 'type' => 'bool'],
            ['key' => 'inventory_sale_policy', 'value' => 'strict', 'group' => 'financial', 'type' => 'string'],
            ['key' => 'inventory_cost_method', 'value' => 'MANUAL', 'group' => 'financial', 'type' => 'string'],
            ['key' => 'invoice_prefix', 'value' => 'SL-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'purchase_prefix', 'value' => 'PR-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'payment_prefix', 'value' => 'PAY-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'adjustment_prefix', 'value' => 'ADJ-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'sales_return_prefix', 'value' => 'SRT-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'purchase_return_prefix', 'value' => 'PRT-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'repair_receipt_prefix', 'value' => 'RPR-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'repair_invoice_prefix', 'value' => 'RS-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'repair_payment_prefix', 'value' => 'RCV-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'expense_prefix', 'value' => 'EXP-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'material_sku_prefix', 'value' => 'M-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'print_paper_invoice', 'value' => 'a4', 'group' => 'print', 'type' => 'string'],
            ['key' => 'print_paper_payment', 'value' => 'a4', 'group' => 'print', 'type' => 'string'],
            ['key' => 'print_paper_repair', 'value' => 'a4', 'group' => 'print', 'type' => 'string'],
            ['key' => 'print_paper_statement', 'value' => 'a4', 'group' => 'print', 'type' => 'string'],
            ['key' => 'print_footer_note', 'value' => '', 'group' => 'print', 'type' => 'string'],
            ['key' => 'print_repair_terms', 'value' => '', 'group' => 'print', 'type' => 'string'],
            ['key' => 'print_show_signatures', 'value' => '1', 'group' => 'print', 'type' => 'bool'],
            ['key' => 'feature_repairs', 'value' => '1', 'group' => 'features', 'type' => 'bool'],
            ['key' => 'feature_technicians', 'value' => '1', 'group' => 'features', 'type' => 'bool'],
            ['key' => 'feature_purchases', 'value' => '1', 'group' => 'features', 'type' => 'bool'],
            ['key' => 'feature_expenses', 'value' => '1', 'group' => 'features', 'type' => 'bool'],
            ['key' => 'feature_reports', 'value' => '1', 'group' => 'features', 'type' => 'bool'],
            ['key' => 'feature_services', 'value' => '1', 'group' => 'features', 'type' => 'bool'],
            ['key' => 'feature_inventory', 'value' => '1', 'group' => 'features', 'type' => 'bool'],
            ['key' => 'feature_sales', 'value' => '1', 'group' => 'features', 'type' => 'bool'],
        ];
    }

    private function permissions(): array
    {
        return [
            ['users.manage', 'إدارة المستخدمين', 'access'],
            ['roles.manage', 'إدارة الأدوار والصلاحيات', 'access'],
            ['settings.manage', 'إدارة الإعدادات', 'access'],
            ['features.manage', 'إدارة وتفعيل ميزات النظام', 'access'],
            ['currencies.manage', 'إدارة العملات وأسعار الصرف', 'access'],
            ['customers.view', 'عرض العملاء', 'customers'],
            ['customers.create', 'إنشاء عميل', 'customers'],
            ['customers.edit', 'تعديل عميل', 'customers'],
            ['customers.delete', 'حذف عميل', 'customers'],
            ['suppliers.view', 'عرض الموردين', 'suppliers'],
            ['suppliers.create', 'إنشاء مورد', 'suppliers'],
            ['suppliers.edit', 'تعديل مورد', 'suppliers'],
            ['suppliers.delete', 'حذف مورد', 'suppliers'],
            ['services.view', 'عرض الخدمات', 'services'],
            ['services.create', 'إنشاء خدمة', 'services'],
            ['services.edit', 'تعديل خدمة', 'services'],
            ['services.delete', 'حذف خدمة', 'services'],
            ['materials.view', 'عرض المواد', 'materials'],
            ['materials.create', 'إنشاء مادة', 'materials'],
            ['materials.edit', 'تعديل مادة', 'materials'],
            ['materials.delete', 'حذف مادة', 'materials'],
            ['inventory.adjust', 'تعديل المخزون', 'inventory'],
            ['inventory.view', 'عرض المخزون', 'inventory'],
            ['sales.view', 'عرض فواتير البيع', 'sales'],
            ['sales.create', 'إنشاء فاتورة بيع', 'sales'],
            ['sales.post', 'اعتماد فاتورة بيع', 'sales'],
            ['sales.cancel', 'إلغاء فاتورة بيع', 'sales'],
            ['sales.return', 'تسجيل مرتجع بيع', 'sales'],
            ['purchases.view', 'عرض فواتير الشراء', 'purchases'],
            ['purchases.create', 'إنشاء فاتورة شراء', 'purchases'],
            ['purchases.post', 'اعتماد فاتورة شراء', 'purchases'],
            ['purchases.cancel', 'إلغاء فاتورة شراء', 'purchases'],
            ['purchases.return', 'تسجيل مرتجع شراء', 'purchases'],
            ['payments.view', 'عرض سندات القبض والدفع', 'payments'],
            ['payments.create', 'إنشاء دفعة', 'payments'],
            ['payments.confirm', 'اعتماد دفعة', 'payments'],
            ['payments.cancel', 'إلغاء دفعة', 'payments'],
            ['statements.view', 'عرض كشوف الحسابات', 'statements'],
            ['reports.view', 'عرض التقارير', 'reports'],
            ['reports.technicians', 'تقرير محاسبة خبراء الصيانة', 'reports'],
            ['reports.technician_repairs', 'تقرير عمولات الخبراء لكل بلاغ', 'reports'],
            ['dashboard.view', 'عرض لوحة التحكم', 'dashboard'],
            ['repairs.view', 'عرض أجهزة الصيانة', 'repairs'],
            ['repairs.create', 'استلام جهاز صيانة', 'repairs'],
            ['repairs.edit', 'تعديل بلاغ صيانة', 'repairs'],
            ['repairs.delete', 'حذف بلاغ صيانة', 'repairs'],
            ['repairs.settle', 'تحصيل وتسوية مالية للصيانة', 'repairs'],
            ['technicians.view', 'عرض خبراء الصيانة', 'technicians'],
            ['technicians.create', 'إنشاء خبير صيانة', 'technicians'],
            ['technicians.edit', 'تعديل خبير صيانة', 'technicians'],
            ['technicians.delete', 'حذف خبير صيانة', 'technicians'],
            ['expenses.view', 'عرض المصاريف', 'expenses'],
            ['expenses.create', 'تسجيل مصروف', 'expenses'],
            ['expenses.edit', 'تعديل مصروف', 'expenses'],
            ['expenses.cancel', 'إلغاء مصروف', 'expenses'],
        ];
    }

    private function rolesSlugs(): array
    {
        return [
            'master' => 'المدير العام',
            'admin' => 'مدير النظام',
            'accountant' => 'محاسب',
            'employee' => 'موظف',
        ];
    }
}
