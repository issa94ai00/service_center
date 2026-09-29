<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 40)->unique();
            $table->string('prefix', 40)->default('');
            $table->bigInteger('last_value')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
