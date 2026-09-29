<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Money taken at the maintenance counter gets its own receipt series (RCV- by
 * default) instead of sharing the counter-sales series.
 *
 * A repair is paid for in two moments — the deposit at intake and the balance
 * at hand-over — and both are booked through the repairs screen, never through
 * the payments screen. Giving them their own numbers means a receipt can be
 * placed by its number alone: RCV- came off a device, PAY- came off a sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('settings')->where('key', 'repair_payment_prefix')->exists()) {
            DB::table('settings')->insert([
                'key' => 'repair_payment_prefix',
                'value' => 'RCV-',
                'group' => 'numbers',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'repair_payment_prefix')->delete();
        DB::table('document_sequences')->where('document_type', 'REPAIR_PAYMENT')->delete();
    }
};
