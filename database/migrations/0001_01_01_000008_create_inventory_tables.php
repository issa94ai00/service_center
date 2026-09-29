<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['PURCHASE', 'SALE', 'ADJUSTMENT', 'PURCHASE_RETURN', 'SALE_RETURN', 'OPENING'])
                ->index();
            $table->enum('direction', ['IN', 'OUT']);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->enum('source_type', [
                'SALES_INVOICE', 'PURCHASE_INVOICE', 'INVENTORY_ADJUSTMENT', 'MANUAL', 'REVERSAL',
            ])->index();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference_no', 50)->nullable();
            $table->date('movement_date');
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('cancels_id')->nullable()->comment('original movement reversed by this row');
            $table->string('cancelled_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['material_id', 'movement_date']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_no', 30)->unique();
            $table->enum('status', ['POSTED', 'CANCELLED'])->default('POSTED')->index();
            $table->string('reason', 255);
            $table->foreignId('adjusted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_adjustment_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->enum('direction', ['IN', 'OUT']);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->string('reason', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_items');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('stock_movements');
    }
};
