<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sales and purchase returns.
 *
 * A return is its own document pointing at the posted invoice it reverses, line
 * by line, so a partial return never edits the invoice it came from: the
 * invoice keeps what was sold, the return says what came back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 30)->unique();
            $table->foreignId('sales_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('return_date');
            $table->enum('status', ['POSTED', 'CANCELLED'])->default('POSTED')->index();
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            // The part of the total that settled the invoice's open balance…
            $table->decimal('applied_amount', 15, 2)->default(0);
            // …and the part handed back in cash; whatever is left of the total
            // stays on the customer's account as credit.
            $table->decimal('refunded_amount', 15, 2)->default(0);
            $table->string('refund_method', 10)->nullable();
            $table->decimal('cost_amount', 15, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'return_date']);
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_item_id')->constrained()->restrictOnDelete();
            $table->enum('line_type', ['SERVICE', 'MATERIAL']);
            $table->foreignId('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->restrictOnDelete();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->decimal('cost_amount', 15, 2)->nullable();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 30)->unique();
            $table->foreignId('purchase_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->date('return_date');
            $table->enum('status', ['POSTED', 'CANCELLED'])->default('POSTED')->index();
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('applied_amount', 15, 2)->default(0);
            $table->decimal('refunded_amount', 15, 2)->default(0);
            $table->string('refund_method', 10)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'return_date']);
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_invoice_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
        });

        // What returns have taken off each invoice's open balance, so that
        // remaining = total − paid − returned holds without re-reading returns.
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->decimal('returned_amount', 15, 2)->default(0)->after('paid_amount');
        });
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->decimal('returned_amount', 15, 2)->default(0)->after('paid_amount');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('source_type', [
                'SALES_INVOICE', 'PURCHASE_INVOICE', 'INVENTORY_ADJUSTMENT', 'MANUAL', 'REVERSAL',
                'SALES_RETURN', 'PURCHASE_RETURN',
            ])->change();
        });
        Schema::table('customer_transactions', function (Blueprint $table) {
            $table->enum('type', [
                'SALES_INVOICE', 'CUSTOMER_PAYMENT', 'OPENING', 'REVERSAL', 'ADJUSTMENT',
                'SALES_RETURN', 'CUSTOMER_REFUND',
            ])->change();
        });
        Schema::table('supplier_transactions', function (Blueprint $table) {
            $table->enum('type', [
                'PURCHASE_INVOICE', 'SUPPLIER_PAYMENT', 'OPENING', 'REVERSAL', 'ADJUSTMENT',
                'PURCHASE_RETURN', 'SUPPLIER_REFUND',
            ])->change();
        });

        // Each return series gets its own prefix, so a return number is never
        // mistaken for an invoice of the same direction.
        foreach (['sales_return_prefix' => 'SRT-', 'purchase_return_prefix' => 'PRT-'] as $key => $value) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key, 'value' => $value, 'group' => 'numbers', 'type' => 'string',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['sales_return_prefix', 'purchase_return_prefix'])->delete();

        Schema::table('supplier_transactions', function (Blueprint $table) {
            $table->enum('type', ['PURCHASE_INVOICE', 'SUPPLIER_PAYMENT', 'OPENING', 'REVERSAL', 'ADJUSTMENT'])->change();
        });
        Schema::table('customer_transactions', function (Blueprint $table) {
            $table->enum('type', ['SALES_INVOICE', 'CUSTOMER_PAYMENT', 'OPENING', 'REVERSAL', 'ADJUSTMENT'])->change();
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('source_type', [
                'SALES_INVOICE', 'PURCHASE_INVOICE', 'INVENTORY_ADJUSTMENT', 'MANUAL', 'REVERSAL',
            ])->change();
        });

        Schema::table('purchase_invoices', fn (Blueprint $table) => $table->dropColumn('returned_amount'));
        Schema::table('sales_invoices', fn (Blueprint $table) => $table->dropColumn('returned_amount'));

        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
