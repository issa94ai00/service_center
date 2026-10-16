<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // admin | signup — who created the shop.
            $table->string('source', 20)->default('admin')->after('status');
            $table->string('signup_ip', 45)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['source', 'signup_ip']);
        });
    }
};
