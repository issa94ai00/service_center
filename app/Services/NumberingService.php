<?php

namespace App\Services;

use App\Models\DocumentSequence;
use App\Models\Material;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class NumberingService
{
    /**
     * Generate the next sequential document number for the given sequence type.
     * Uses a row lock so concurrent posts cannot collide.
     */
    public function next(string $documentType): string
    {
        $prefix = $this->prefixFor($documentType);

        $sequence = DB::transaction(function () use ($documentType, $prefix) {
            /** @var DocumentSequence $sequence */
            $sequence = DocumentSequence::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['document_type' => $documentType],
                    ['prefix' => $prefix, 'last_value' => 0]
                );

            $next = $sequence->last_value + 1;
            $sequence->update(['last_value' => $next, 'prefix' => $prefix]);

            return $sequence;
        });

        return $sequence->prefix.str_pad((string) $sequence->last_value, 6, '0', STR_PAD_LEFT);
    }

    /**
     * A house code for a material nobody typed a code for.
     *
     * البضاعة تصل أحياناً بلا رمز من مصنعها، والموظّف واقف أمام المورد لا أمام
     * شاشة المواد: إلزامه باختراع رمز فريد في تلك اللحظة يُنتج رموزاً مثل «1»
     * و«جديد». الرقم التسلسلي هنا يحرّره من القرار، ويبقى الحقل مفتوحاً لمن
     * يمسك دفتر ترميز خاصاً به.
     */
    public function nextMaterialSku(): string
    {
        // A code typed by hand may already sit where the counter is about to
        // land. Walk forward instead of failing a save the clerk cannot fix.
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $sku = $this->next('MATERIAL_SKU');

            if (! Material::withTrashed()->where('sku', $sku)->exists()) {
                return $sku;
            }
        }

        // خمسون محاولة تعني أن السلسلة كلها مأخوذة يدوياً؛ رمز عشوائي أفضل من
        // رفض الحفظ.
        return 'M-'.strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * The prefix is a setting so the shop can brand its numbers; each document
     * type defaults to its own letters so a series never inherits another's.
     */
    protected function prefixFor(string $documentType): string
    {
        return match ($documentType) {
            'SALES_INVOICE' => Setting::get('invoice_prefix', 'SL-'),
            // Invoices raised at repair settlement run their own series, so a
            // shop counter and a hand-over counter never share a number space.
            'REPAIR_INVOICE' => Setting::get('repair_invoice_prefix', 'RS-'),
            'PURCHASE_INVOICE' => Setting::get('purchase_prefix', 'PR-'),
            'PAYMENT' => Setting::get('payment_prefix', 'PAY-'),
            // Money taken at the maintenance counter — the deposit at intake and
            // the balance at hand-over — runs its own receipt series, so a
            // receipt number alone says which counter it came off.
            'REPAIR_PAYMENT' => Setting::get('repair_payment_prefix', 'RCV-'),
            'INVENTORY_ADJUSTMENT' => Setting::get('adjustment_prefix', 'ADJ-'),
            // المرتجعات سلسلتان مستقلتان: رقم المرتجع وحده يقول إن البضاعة
            // عادت، ومن أيّ اتجاه.
            'SALES_RETURN' => Setting::get('sales_return_prefix', 'SRT-'),
            'PURCHASE_RETURN' => Setting::get('purchase_return_prefix', 'PRT-'),
            // مصاريف الصندوق: سلسلة مستقلة حتى يقول رقم السند وحده إنه مصروف
            // تشغيل لا دفعة لمورد.
            'EXPENSE' => Setting::get('expense_prefix', 'EXP-'),
            // رموز المواد المولَّدة تلقائياً حين تُضاف مادة مجهولة أثناء الشراء.
            'MATERIAL_SKU' => Setting::get('material_sku_prefix', 'M-'),
            default => Setting::get('doc_prefix', ''),
        };
    }
}
