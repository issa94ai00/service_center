<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('device_type', 60)->default('جوال');
            $table->string('brand', 60)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('color', 40)->nullable();
            $table->string('serial_number', 80)->nullable();
            $table->text('accessories')->nullable();
            $table->text('reported_issue');
            $table->text('notes')->nullable();
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->enum('status', [
                'RECEIVED', 'DIAGNOSING', 'UNDER_REPAIR', 'WAITING_APPROVAL', 'READY', 'DELIVERED', 'CANCELLED',
            ])->default('RECEIVED');
            $table->dateTime('received_at');
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repairs');
    }
};
