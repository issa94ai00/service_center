<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * بيانات اختبار: مواد، عملاء، موردون، خدمات ومخزون افتتاحي.
 *
 * مقصود أن يكون قابلاً لإعادة التشغيل (idempotent): المواد والخدمات والأطراف
 * تُحدَّث بالمفتاح الطبيعي، وحركة المخزون الافتتاحية تُضاف فقط للسلعة التي
 * لا تملك أي حركة بعد — فلا تتكرر الأرصدة عند التشغيل المتكرر.
 */
class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $unit = fn (string $nameAr): int => DB::table('service_units')
            ->where('name_ar', $nameAr)
            ->value('id');

        $now = now();

        // ----------------------------------------------------------------فئات المواد
        $categoryIds = [];
        foreach ($this->categories() as $name) {
            $categoryIds[$name] = DB::table('material_categories')->updateOrInsert(
                ['name' => $name],
                ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $categoryId = fn (string $name): int => DB::table('material_categories')
            ->where('name', $name)
            ->value('id');

        // ----------------------------------------------------------------المواد
        $userId = DB::table('users')->value('id');
        $openingDate = '2026-01-01';

        foreach ($this->materials() as $material) {
            $existing = DB::table('materials')->where('sku', $material['sku'])->first();

            if ($existing) {
                DB::table('materials')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $material['name'],
                        'category_id' => $categoryId($material['category']),
                        'unit_id' => $unit($material['unit']),
                        'cost_price' => $material['cost_price'],
                        'sale_price' => $material['sale_price'],
                        'min_stock' => $material['min_stock'],
                        'stock_quantity' => $material['stock'],
                        'is_active' => true,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('materials')->insert([
                    'name' => $material['name'],
                    'sku' => $material['sku'],
                    'category_id' => $categoryId($material['category']),
                    'unit_id' => $unit($material['unit']),
                    'cost_price' => $material['cost_price'],
                    'sale_price' => $material['sale_price'],
                    'min_stock' => $material['min_stock'],
                    'stock_quantity' => $material['stock'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // حركة افتتاحية لكل سلعة لا حركة لها بعد، حتى يتطابق السجل مع الرصيد.
        foreach (DB::table('materials')->get() as $material) {
            if ((float) $material->stock_quantity <= 0) {
                continue;
            }

            $hasMovement = DB::table('stock_movements')
                ->where('material_id', $material->id)
                ->exists();

            if ($hasMovement) {
                continue;
            }

            DB::table('stock_movements')->insert([
                'material_id' => $material->id,
                'type' => 'OPENING',
                'direction' => 'IN',
                'quantity' => $material->stock_quantity,
                'unit_price' => $material->cost_price,
                'source_type' => 'MANUAL',
                'source_id' => null,
                'reference_no' => 'OPEN-INIT',
                'movement_date' => $openingDate,
                'description' => 'رصيد افتتاحي',
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ----------------------------------------------------------------الخدمات
        foreach ($this->services() as $service) {
            DB::table('services')->updateOrInsert(
                ['name' => $service['name']],
                [
                    'description' => $service['description'] ?? null,
                    'type' => $service['type'],
                    'unit_id' => isset($service['unit']) ? $unit($service['unit']) : null,
                    'default_price' => $service['default_price'] ?? null,
                    'is_active' => true,
                    'updated_at' => $now,
                ]
            );
        }

        // ----------------------------------------------------------------العملاء
        foreach ($this->customers() as $customer) {
            DB::table('customers')->updateOrInsert(
                ['name' => $customer['name']],
                array_merge(
                    array_diff_key($customer, ['name' => true]),
                    ['is_active' => true, 'updated_at' => $now]
                )
            );
        }

        // ----------------------------------------------------------------الموردون
        foreach ($this->suppliers() as $supplier) {
            DB::table('suppliers')->updateOrInsert(
                ['name' => $supplier['name']],
                array_merge(
                    array_diff_key($supplier, ['name' => true]),
                    ['is_active' => true, 'updated_at' => $now]
                )
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function categories(): array
    {
        return [
            'شاشات وزجاج',
            'بطاريات',
            'إكسسوارات',
            'قطع صيانة',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function materials(): array
    {
        return [
            [
                'name' => 'شاشة آيفون 11',
                'sku' => 'MAT-SH-IP11',
                'category' => 'شاشات وزجاج',
                'unit' => 'قطعة',
                'cost_price' => '250000.00',
                'sale_price' => '350000.00',
                'min_stock' => 5,
                'stock' => 12,
            ],
            [
                'name' => 'شاشة آيفون 12',
                'sku' => 'MAT-SH-IP12',
                'category' => 'شاشات وزجاج',
                'unit' => 'قطعة',
                'cost_price' => '300000.00',
                'sale_price' => '400000.00',
                'min_stock' => 5,
                'stock' => 8,
            ],
            [
                'name' => 'شاشة هواوي P30',
                'sku' => 'MAT-SH-HP30',
                'category' => 'شاشات وزجاج',
                'unit' => 'قطعة',
                'cost_price' => '220000.00',
                'sale_price' => '320000.00',
                'min_stock' => 5,
                'stock' => 7,
            ],
            [
                'name' => 'بطارية آيفون 11',
                'sku' => 'MAT-BA-IP11',
                'category' => 'بطاريات',
                'unit' => 'قطعة',
                'cost_price' => '45000.00',
                'sale_price' => '65000.00',
                'min_stock' => 10,
                'stock' => 25,
            ],
            [
                'name' => 'بطارية سامسونج A52',
                'sku' => 'MAT-BA-SA52',
                'category' => 'بطاريات',
                'unit' => 'قطعة',
                'cost_price' => '40000.00',
                'sale_price' => '60000.00',
                'min_stock' => 10,
                'stock' => 18,
            ],
            [
                'name' => 'غطاء حماية زجاجي',
                'sku' => 'MAT-GL-GLASS',
                'category' => 'إكسسوارات',
                'unit' => 'قطعة',
                'cost_price' => '3000.00',
                'sale_price' => '8000.00',
                'min_stock' => 50,
                'stock' => 120,
            ],
            [
                'name' => 'كابل شحن USB-C',
                'sku' => 'MAT-CB-USBC',
                'category' => 'إكسسوارات',
                'unit' => 'قطعة',
                'cost_price' => '25000.00',
                'sale_price' => '40000.00',
                'min_stock' => 20,
                'stock' => 45,
            ],
            [
                'name' => 'شاحن 20 واط',
                'sku' => 'MAT-CH-20W',
                'category' => 'إكسسوارات',
                'unit' => 'قطعة',
                'cost_price' => '60000.00',
                'sale_price' => '90000.00',
                'min_stock' => 10,
                'stock' => 30,
            ],
            [
                'name' => 'سماعة بلوتوث',
                'sku' => 'MAT-AC-BTH',
                'category' => 'إكسسوارات',
                'unit' => 'جهاز',
                'cost_price' => '90000.00',
                'sale_price' => '150000.00',
                'min_stock' => 5,
                'stock' => 10,
            ],
            [
                'name' => 'أنبوب لصق حراري',
                'sku' => 'MAT-TG-HEAT',
                'category' => 'قطع صيانة',
                'unit' => 'قطعة',
                'cost_price' => '15000.00',
                'sale_price' => '25000.00',
                'min_stock' => 2,
                'stock' => 6,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function services(): array
    {
        return [
            [
                'name' => 'فحص جهاز',
                'description' => 'فحص شامل لأعطال الجهاز وتحديد سبب العطل',
                'type' => 'MANUAL',
                'unit' => 'جهاز',
                'default_price' => '25000.00',
            ],
            [
                'name' => 'تغيير شاشة',
                'description' => 'تركيب شاشة بديلة مع تجربة كاملة',
                'type' => 'MANUAL',
                'unit' => 'جهاز',
                'default_price' => '100000.00',
            ],
            [
                'name' => 'تغيير بطارية',
                'description' => 'استبدال البطارية بطارية أصلية',
                'type' => 'MANUAL',
                'unit' => 'جهاز',
                'default_price' => '50000.00',
            ],
            [
                'name' => 'صيانة لوحة أم',
                'description' => 'إصلاح أعطال البورد (سير، آيسي، مقاومات)',
                'type' => 'METER_BASED',
                'unit' => 'ساعة',
                'default_price' => '150000.00',
            ],
            [
                'name' => 'معالجة ضرر مياه',
                'description' => 'تنظيف ومعالجة دائرة بعد التعرض للرطوبة',
                'type' => 'METER_BASED',
                'unit' => 'ساعة',
                'default_price' => '60000.00',
            ],
            [
                'name' => 'تركيب غطاء حماية',
                'description' => 'تقنيب غطاء حماية زجاجي للشاشة',
                'type' => 'MANUAL',
                'unit' => 'جهاز',
                'default_price' => '5000.00',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function customers(): array
    {
        return [
            [
                'name' => 'أحمد خالد محمد',
                'phone' => '011-2213344',
                'mobile' => '0933-123456',
                'email' => 'ahmad.khaled@example.com',
                'address' => 'شارع الثورة، دمشق',
                'city' => 'دمشق',
                'opening_balance' => '0.00',
                'notes' => null,
            ],
            [
                'name' => 'سارة علي حسن',
                'mobile' => '0944-222111',
                'email' => 'sara.ali@example.com',
                'city' => 'دمشق',
                'opening_balance' => '0.00',
            ],
            [
                'name' => 'محمد عمر عابد',
                'mobile' => '0955-333222',
                'city' => 'حلب',
                'opening_balance' => '0.00',
            ],
            [
                'name' => 'شركة اليمامة للاتصالات',
                'company' => 'شركة اليمامة للاتصالات',
                'phone' => '011-5411000',
                'email' => 'info@yammah.sy',
                'address' => 'المزة، فيلا شرقية، دمشق',
                'city' => 'دمشق',
                'tax_number' => '1234567',
                'opening_balance' => '-250000.00',
            ],
            [
                'name' => 'لبنى يوسف',
                'mobile' => '0966-444333',
                'city' => 'حمص',
                'opening_balance' => '0.00',
            ],
            [
                'name' => 'كريم نبيل سعيد',
                'mobile' => '0977-555444',
                'email' => 'kareem.nabeel@example.com',
                'city' => 'اللاذقية',
                'opening_balance' => '0.00',
            ],
            [
                'name' => 'مرام سامي',
                'mobile' => '0988-666555',
                'city' => 'دمشق',
                'opening_balance' => '0.00',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function suppliers(): array
    {
        return [
            [
                'name' => 'مؤسسة النور للإكسسوارات',
                'company' => 'مؤسسة النور للإكسسوارات',
                'phone' => '021-2233445',
                'email' => 'sales@alnoor.sy',
                'address' => 'المنطقة الصناعية، حلب',
                'city' => 'حلب',
                'tax_number' => '7654321',
                'opening_balance' => '0.00',
            ],
            [
                'name' => 'شركة الشام لتجارة الجوالات',
                'phone' => '011-3322110',
                'email' => 'info@alsham-mobiles.sy',
                'address' => 'شارع الحمرا، دمشق',
                'city' => 'دمشق',
                'opening_balance' => '350000.00',
            ],
            [
                'name' => 'مكتب الفيحاء لقطع الغيار',
                'phone' => '031-2255667',
                'address' => 'شارع أبو عبيدة، حمص',
                'city' => 'حمص',
                'opening_balance' => '0.00',
            ],
            [
                'name' => 'دار السلام للاستيراد',
                'phone' => '041-3322110',
                'email' => 'info@darsalam.sy',
                'city' => 'اللاذقية',
                'opening_balance' => '0.00',
            ],
        ];
    }
}