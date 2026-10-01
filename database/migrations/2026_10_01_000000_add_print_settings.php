<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Printing preferences: a default paper per document kind (A4, A5 or a
 * thermal roll), plus the text printed at the foot of documents. Every paper
 * defaults to A4 so documents keep printing exactly as they did.
 */
return new class extends Migration
{
    protected const SETTINGS = [
        ['print_paper_invoice', 'a4', 'string'],
        ['print_paper_payment', 'a4', 'string'],
        ['print_paper_repair', 'a4', 'string'],
        ['print_paper_statement', 'a4', 'string'],
        ['print_footer_note', '', 'string'],
        ['print_repair_terms', '', 'string'],
        ['print_show_signatures', '1', 'bool'],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as [$key, $value, $type]) {
            if (DB::table('settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'group' => 'print',
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::SETTINGS, 0))->delete();
    }
};
