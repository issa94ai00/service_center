<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database backups of every shop and of the central registry, plus the flag
 * that closes a shop to its users while its database is being restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            // Null for the central database. Kept when the shop is deleted, so
            // its last backups outlive it.
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('database', 64)->index();
            // The shop's name when the backup was taken.
            $table->string('label');
            // scheduled | manual | pre_restore | pre_migrate | pre_delete
            $table->string('trigger', 20)->index();
            // running | completed | failed
            $table->string('status', 20)->default('running')->index();
            // Relative to the backups disk.
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('checksum', 64)->nullable();
            // disabled | uploaded | failed
            $table->string('offsite_status', 20)->default('disabled');
            $table->text('offsite_error')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->index(['database', 'created_at']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('maintenance_at')->nullable()->after('ssl_message');
            $table->string('maintenance_reason')->nullable()->after('maintenance_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['maintenance_at', 'maintenance_reason']);
        });

        Schema::dropIfExists('backups');
    }
};
