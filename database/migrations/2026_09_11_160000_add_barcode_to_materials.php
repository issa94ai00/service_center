<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الباركود المطبوع على المادة، منفصلاً عن رمزها الداخلي.
 *
 * الـ sku رمز المحل الذي يختاره بنفسه؛ أمّا الباركود فما طُبع على العبوة من
 * مصنعها (EAN‑13 غالباً) ولا سلطة للمحل عليه. خلطهما في عمود واحد يعني أن
 * إدخال مادة جديدة يفرض إمّا تغيير ترقيم المحل أو التخلّي عن المسح.
 *
 * يبقى nullable: أكثر الإكسسوارات تصل بلا باركود مقروء، وهذه لا تُمسح بل
 * تُبحث بالاسم. وMySQL يسمح بتكرار NULL في الفهرس الفريد، فالقيد يمنع تكرار
 * الباركود الحقيقي دون أن يمنع تعدّد المواد التي لا باركود لها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('barcode', 64)->nullable()->after('sku');
            $table->unique('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropColumn('barcode');
        });
    }
};
