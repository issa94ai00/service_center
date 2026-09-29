<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('documents_sequences');
    }

    public function down(): void
    {
        Schema::create('documents_sequences', function (Blueprint $table) {
            $table->id();
            $table->enum('document_type', ['SALES_INVOICE', 'PURCHASE_INVOICE', 'PAYMENT'])->unique();
            $table->string('prefix', 10);
            $table->unsignedBigInteger('last_value')->default(0);
        });
    }
};
