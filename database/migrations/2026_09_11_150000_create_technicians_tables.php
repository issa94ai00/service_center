<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * خبير الصيانة: طرف ثالث بجانب العميل والمورد.
 *
 * الخبير يعمل بنسبة من التكلفة النهائية للبلاغ، فله دفتر حسابات مثل المورد
 * تماماً: كل بلاغ يُسوَّى يقيّد له عمولته دائنة (يستحق)، وكل دفعة تُصرف له
 * تُقيَّد مدينة (سُدِّد). الرصيد = مدين − دائن، وسالبه يعني أن المحل ما زال
 * مديناً للخبير — نفس اصطلاح الموردين، ولهذا يقلب التقرير الإشارة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technicians', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('specialty', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            // النسبة الافتراضية من التكلفة النهائية للبلاغ (٪).
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('soft_deleted_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('technician_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained()->restrictOnDelete();
            $table->date('transaction_date');
            $table->enum('type', [
                'REPAIR_COMMISSION', 'TECHNICIAN_PAYMENT', 'OPENING', 'REVERSAL', 'ADJUSTMENT',
            ])->index();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('ledger_reconciled_at')->nullable();
            $table->timestamps();

            $table->index(['technician_id', 'transaction_date', 'id']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::table('repairs', function (Blueprint $table) {
            $table->foreignId('technician_id')->nullable()->after('customer_id')
                ->constrained('technicians')->nullOnDelete();
            // لقطة وقت التسوية، لا مرجع حيّ لنسبة الخبير: تعديل نسبته لاحقاً
            // يجب ألّا يعيد حساب عمولة بلاغ أُقفل وقُيِّد في دفتره.
            $table->decimal('commission_rate', 5, 2)->nullable()->after('final_cost');
            $table->decimal('commission_amount', 15, 2)->nullable()->after('commission_rate');

            $table->index('technician_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('technician_id')->nullable()->after('supplier_id')
                ->constrained('technicians')->restrictOnDelete();
        });

        $this->widenPaymentType(['CUSTOMER', 'SUPPLIER', 'TECHNICIAN']);

        $this->grantPermissions([
            ['technicians.view', 'عرض خبراء الصيانة'],
            ['technicians.create', 'إنشاء خبير صيانة'],
            ['technicians.edit', 'تعديل خبير صيانة'],
            ['technicians.delete', 'حذف خبير صيانة'],
        ]);
    }

    public function down(): void
    {
        $slugs = ['technicians.view', 'technicians.create', 'technicians.edit', 'technicians.delete'];
        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        // تُصفَّر قبل تضييق الـ enum، وإلّا رفض MySQL الصفوف التي لم تعد صالحة.
        DB::table('payments')->where('payment_type', 'TECHNICIAN')->delete();
        $this->widenPaymentType(['CUSTOMER', 'SUPPLIER']);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('technician_id');
        });

        Schema::table('repairs', function (Blueprint $table) {
            $table->dropForeign(['technician_id']);
            $table->dropIndex(['technician_id']);
            $table->dropColumn(['technician_id', 'commission_rate', 'commission_amount']);
        });

        Schema::dropIfExists('technician_transactions');
        Schema::dropIfExists('technicians');
    }

    /**
     * توسيع enum نوع الدفعة.
     *
     * MySQL يعدّل العمود في مكانه؛ أمّا SQLite فيخزّن الـ enum كنص مع قيد
     * CHECK، ولا سبيل لتعديله إلّا بإعادة بناء الجدول — وهو ما يفعله
     * `change()`. لذلك لكل محرّك طريقه.
     *
     * @param  array<int, string>  $values
     */
    private function widenPaymentType(array $values): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $list = collect($values)->map(fn (string $v) => "'{$v}'")->join(',');
            DB::statement("ALTER TABLE payments MODIFY payment_type ENUM({$list}) NOT NULL");

            return;
        }

        Schema::table('payments', function (Blueprint $table) use ($values) {
            $table->enum('payment_type', $values)->change();
        });
    }

    /**
     * تُضاف الصلاحيات وتُمنح للأدوار التي تملك أصلاً صلاحيات الموردين، حتى لا
     * يحتاج تركيب قائم إلى إعادة تعبئة البذور.
     *
     * @param  array<int, array{0: string, 1: string}>  $permissions
     */
    private function grantPermissions(array $permissions): void
    {
        $roleIds = DB::table('roles')->whereIn('slug', ['admin', 'accountant'])->pluck('id');

        foreach ($permissions as [$slug, $name]) {
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'slug' => $slug,
                    'group' => 'technicians',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

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
    }
};
