<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }
        $permissions = [
            'users.manage' => ['name' => 'إدارة المستخدمين', 'group' => 'access'],
            'roles.manage' => ['name' => 'إدارة الأدوار والصلاحيات', 'group' => 'access'],
            'settings.manage' => ['name' => 'إدارة الإعدادات العامة', 'group' => 'access'],
            'features.manage' => ['name' => 'إدارة وتفعيل ميزات النظام', 'group' => 'access'],
            'customers.view' => ['name' => 'عرض بيانات العملاء', 'group' => 'customers'],
            'customers.create' => ['name' => 'إضافة عميل جديد', 'group' => 'customers'],
            'customers.edit' => ['name' => 'تعديل بيانات العميل', 'group' => 'customers'],
            'customers.delete' => ['name' => 'حذف عميل', 'group' => 'customers'],
            'suppliers.view' => ['name' => 'عرض بيانات الموردين', 'group' => 'suppliers'],
            'suppliers.create' => ['name' => 'إضافة مورد جديد', 'group' => 'suppliers'],
            'suppliers.edit' => ['name' => 'تعديل بيانات المورد', 'group' => 'suppliers'],
            'suppliers.delete' => ['name' => 'حذف مورد', 'group' => 'suppliers'],
            'services.view' => ['name' => 'عرض دليل الخدمات', 'group' => 'services'],
            'services.create' => ['name' => 'إضافة خدمة جديدة', 'group' => 'services'],
            'services.edit' => ['name' => 'تعديل خدمة', 'group' => 'services'],
            'services.delete' => ['name' => 'حذف خدمة', 'group' => 'services'],
            'materials.view' => ['name' => 'عرض دليل المواد والقطع', 'group' => 'materials'],
            'materials.create' => ['name' => 'إضافة مادة جديدة', 'group' => 'materials'],
            'materials.edit' => ['name' => 'تعديل مادة', 'group' => 'materials'],
            'materials.delete' => ['name' => 'حذف مادة', 'group' => 'materials'],
            'inventory.adjust' => ['name' => 'إجراء وتعديل الجرد المخزني', 'group' => 'inventory'],
            'inventory.view' => ['name' => 'عرض المخزون وحركة المواد', 'group' => 'inventory'],
            'sales.view' => ['name' => 'عرض فواتير البيع', 'group' => 'sales'],
            'sales.create' => ['name' => 'إنشاء مسودة فاتورة بيع', 'group' => 'sales'],
            'sales.post' => ['name' => 'اعتماد وترحيل فاتورة بيع', 'group' => 'sales'],
            'sales.cancel' => ['name' => 'إلغاء فاتورة بيع', 'group' => 'sales'],
            'purchases.view' => ['name' => 'عرض فواتير الشراء', 'group' => 'purchases'],
            'purchases.create' => ['name' => 'إنشاء مسودة فاتورة شراء', 'group' => 'purchases'],
            'purchases.post' => ['name' => 'اعتماد وترحيل فاتورة شراء', 'group' => 'purchases'],
            'purchases.cancel' => ['name' => 'إلغاء فاتورة شراء', 'group' => 'purchases'],
            'payments.create' => ['name' => 'تسجيل سند قبض / دفع', 'group' => 'payments'],
            'payments.confirm' => ['name' => 'اعتماد وتأكيد الدفعة', 'group' => 'payments'],
            'payments.cancel' => ['name' => 'إلغاء دفعة مسجلة', 'group' => 'payments'],
            'statements.view' => ['name' => 'عرض كشوف الحسابات المالية', 'group' => 'statements'],
            'reports.view' => ['name' => 'عرض التقارير الإحصائية والمالية', 'group' => 'reports'],
            'dashboard.view' => ['name' => 'عرض لوحة التحكم والمؤشرات', 'group' => 'dashboard'],
            'repairs.view' => ['name' => 'عرض أجهزة وبلاغات الصيانة', 'group' => 'repairs'],
            'repairs.create' => ['name' => 'استلام جهاز صيانة جديد', 'group' => 'repairs'],
            'repairs.edit' => ['name' => 'تعديل بلاغ الصيانة', 'group' => 'repairs'],
            'repairs.delete' => ['name' => 'حذف بلاغ صيانة', 'group' => 'repairs'],
            'repairs.settle' => ['name' => 'تحصيل وتسوية فواتير الصيانة', 'group' => 'repairs'],
            'technicians.view' => ['name' => 'عرض خبراء وفنيي الصيانة', 'group' => 'technicians'],
            'technicians.create' => ['name' => 'إضافة فني صيانة جديد', 'group' => 'technicians'],
            'technicians.edit' => ['name' => 'تعديل بيانات فني الصيانة', 'group' => 'technicians'],
            'technicians.delete' => ['name' => 'حذف فني صيانة', 'group' => 'technicians'],
            'expenses.view' => ['name' => 'عرض سجل المصاريف', 'group' => 'expenses'],
            'expenses.create' => ['name' => 'تسجيل مصروف جديد', 'group' => 'expenses'],
            'expenses.edit' => ['name' => 'تعديل مصروف', 'group' => 'expenses'],
            'expenses.cancel' => ['name' => 'إلغاء مصروف', 'group' => 'expenses'],
        ];

        foreach ($permissions as $slug => $data) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'group' => $data['group'],
                ]
            );
        }
    }

    public function down(): void
    {
    }
};
