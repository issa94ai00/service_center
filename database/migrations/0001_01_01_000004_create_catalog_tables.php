<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_units', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 100);
            $table->string('name_en', 100)->nullable();
            $table->string('symbol', 20)->nullable();
            $table->enum('category', ['LENGTH', 'AREA', 'VOLUME', 'COUNT', 'TIME', 'OTHER'])
                ->default('COUNT');
            $table->boolean('is_measurable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('type', ['METER_BASED', 'MANUAL'])->default('METER_BASED');
            $table->foreignId('unit_id')->nullable()->constrained('service_units')->nullOnDelete();
            $table->decimal('default_price', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('parent_id')->nullable()->constrained('material_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('material_categories')->nullOnDelete();
            $table->string('name', 150);
            $table->string('sku', 50)->unique();
            $table->foreignId('unit_id')->constrained('service_units')->restrictOnDelete();
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->decimal('stock_quantity', 15, 3)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
        Schema::dropIfExists('material_categories');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_units');
    }
};
