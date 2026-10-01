<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materials and services are archived rather than deleted: an item with sales
 * or stock history can leave the catalogue while every past invoice, movement
 * and report still names it.
 *
 * The SKU and barcode unique indexes deliberately keep covering archived rows,
 * so a restore can never collide with a code reused in the meantime.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['materials', 'services'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['materials', 'services'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
