<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            // ================================================================
            // 1. IDENTIFICADOR
            // ================================================================
            $table->id();

            // ================================================================
            // 2. INFORMACIÓN DEL BANCO
            // ================================================================
            $table->string('name', 100);
            $table->string('code', 20)->nullable()->unique();
            $table->string('country_code', 2)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('logo_path', 255)->nullable();

            // ================================================================
            // 3. ESTADO
            // ================================================================
            $table->boolean('is_active')->default(true);

            // ================================================================
            // 4. TIMESTAMPS
            // ================================================================
            $table->timestamps();

            // ================================================================
            // 5. ÍNDICES
            // ================================================================
            $table->index('code');
            $table->index('is_active');
            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};