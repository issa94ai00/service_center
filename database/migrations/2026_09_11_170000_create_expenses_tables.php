<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مصاريف الصندوق: المال الذي يخرج من الدرج ولا يقابله مورد ولا فاتورة.
 *
 * إيجار المحل وفاتورة الكهرباء وأجرة الشحن وضيافة الزبائن — كلها نقد خرج
 * فعلاً، ولا مكان له في سندات الدفع لأنها تُخصَّص على فواتير شراء، ولا في
 * دفاتر العملاء والموردين لأن لا طرف لها يُمسك له حساب.
 *
 * لذلك لها جدولها: سند واحد مسطّح يُسجَّل معتمداً لحظة صرفه — فالنقد خرج قبل
 * أن يُكتب، ولا معنى لمسودة مصروف — ويُلغى بسبب مكتوب إن أُخطئ فيه، فلا يُحذف
 * رقم من السلسلة ولا يختفي صرفٌ من التاريخ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_no', 50)->nullable()->unique();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['CASH', 'BANK', 'OTHER'])->default('CASH');
            $table->date('expense_date');
            // الجهة التي قُبض منها المبلغ: شركة الكهرباء، صاحب المحل، السائق.
            // نصٌّ حرّ لا مفتاح: هذه جهات تُذكر ولا يُمسك لها حساب.
            $table->string('payee', 150)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['POSTED', 'CANCELLED'])->default('POSTED')->index();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['expense_date', 'id']);
            $table->index(['expense_category_id', 'expense_date']);
        });

        $this->seedSetting();
        $this->seedCategories();
        $this->grantPermissions();
    }

    public function down(): void
    {
        $slugs = ['expenses.view', 'expenses.create', 'expenses.edit', 'expenses.cancel'];
        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        DB::table('settings')->where('key', 'expense_prefix')->delete();
        DB::table('document_sequences')->where('document_type', 'EXPENSE')->delete();

        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }

    /** سلسلة ترقيم مستقلة: رقم السند وحده يقول إنه مصروف لا سند دفع لمورد. */
    private function seedSetting(): void
    {
        if (DB::table('settings')->where('key', 'expense_prefix')->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'key' => 'expense_prefix',
            'value' => 'EXP-',
            'group' => 'numbers',
            'type' => 'string',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * أصناف تكفي محلاً من يومه الأول، فلا تبدأ الشاشة فارغة تطلب من المستخدم
     * تأليف تصنيف قبل أن يسجّل أول مصروف. تُعدَّل وتُعطَّل من الشاشة نفسها.
     */
    private function seedCategories(): void
    {
        $categories = [
            ['إيجار المحل', 'الإيجار الشهري أو السنوي للمحل والمستودع'],
            ['كهرباء ومولّدة', 'فواتير الكهرباء واشتراك المولّدة والوقود'],
            ['ماء', 'فواتير المياه'],
            ['إنترنت واتصالات', 'اشتراك الإنترنت وأرصدة الهواتف'],
            ['رواتب وأجور', 'رواتب الموظفين والأجور اليومية'],
            ['مواصلات وشحن', 'أجور النقل وشحن البضاعة والمشاوير'],
            ['ضيافة ونثريات', 'ضيافة الزبائن والمصاريف النثرية الصغيرة'],
            ['قرطاسية ومطبوعات', 'الدفاتر والفواتير المطبوعة والحبر'],
            ['صيانة وتجهيزات المحل', 'إصلاح وتجهيز المحل وأدوات العمل'],
            ['دعاية وإعلان', 'اللافتات والإعلانات المدفوعة'],
            ['رسوم وضرائب', 'الرسوم الحكومية والتراخيص والضرائب'],
            ['مصاريف أخرى', 'ما لا يندرج تحت صنف آخر'],
        ];

        foreach ($categories as [$name, $description]) {
            if (DB::table('expense_categories')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('expense_categories')->insert([
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * تُمنح للأدوار التي تدير الصندوق أصلاً، حتى لا يحتاج تركيب قائم إلى
     * إعادة تعبئة البذور ليرى الشاشة الجديدة.
     */
    private function grantPermissions(): void
    {
        $permissions = [
            ['expenses.view', 'عرض المصاريف'],
            ['expenses.create', 'تسجيل مصروف'],
            ['expenses.edit', 'تعديل مصروف'],
            ['expenses.cancel', 'إلغاء مصروف'],
        ];

        $roleIds = DB::table('roles')->whereIn('slug', ['admin', 'accountant'])->pluck('id');

        foreach ($permissions as [$slug, $name]) {
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'slug' => $slug,
                    'group' => 'expenses',
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
