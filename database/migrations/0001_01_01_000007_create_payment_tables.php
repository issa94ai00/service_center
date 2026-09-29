<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->enum('payment_type', ['CUSTOMER', 'SUPPLIER'])->index();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['CASH', 'BANK', 'OTHER'])->default('CASH');
            $table->string('reference_no', 50)->nullable();
            $table->date('payment_date');
            $table->enum('status', ['DRAFT', 'POSTED', 'CANCELLED'])->default('DRAFT')->index();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->enum('invoice_type', ['SALES', 'PURCHASE']);
            $table->foreignId('sales_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
