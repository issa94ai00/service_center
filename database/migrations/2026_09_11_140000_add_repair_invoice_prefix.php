<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair settlement invoices get their own number series (RS- by default)
 * rather than sharing the counter-sales series. The invoice needs to say it
 * settled a repair, so this adds the missing repairs→invoice mirror link and
 * backfills invoices already issued at settlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            // Mirror of repairs.sales_invoice_id: lets the ledger tell a
            // settlement invoice apart from a counter sale at a look.
            $table->foreignId('repair_id')->nullable()->after('customer_id')
                ->constrained('repairs')->nullOnDelete();
        });

        if (! DB::table('settings')->where('key', 'repair_invoice_prefix')->exists()) {
            DB::table('settings')->insert([
                'key' => 'repair_invoice_prefix',
                'value' => 'RS-',
                'group' => 'numbers',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Backfill so invoices settled before this migration are recognised as
        // repair invoices too.
        DB::table('repairs')
            ->whereNotNull('sales_invoice_id')
            ->orderBy('id')
            ->each(function (object $repair): void {
                DB::table('sales_invoices')
                    ->where('id', $repair->sales_invoice_id)
                    ->whereNull('repair_id')
                    ->update(['repair_id' => $repair->id]);
            });
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'repair_invoice_prefix')->delete();

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('repair_id');
        });
    }
};