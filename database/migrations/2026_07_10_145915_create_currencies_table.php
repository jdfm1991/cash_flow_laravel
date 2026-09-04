<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            // ================================================================
            // 1. IDENTIFICADOR
            // ================================================================
            $table->id();

            // ================================================================
            // 2. INFORMACIÓN DE LA MONEDA
            // ================================================================
            $table->string('code', 3)->unique();        // USD, EUR, VES, COP
            $table->string('name', 50);                 // Dólar Estadounidense
            $table->string('symbol', 5);                // $, €, Bs.S
            $table->integer('decimal_places')->default(2);

            // ================================================================
            // 3. CONFIGURACIÓN DEL SISTEMA
            // ================================================================
            $table->boolean('is_base')->default(false);   // Moneda funcional
            $table->boolean('is_default')->default(false); // Moneda de visualización
            $table->boolean('is_active')->default(true);

            // ================================================================
            // 4. TIMESTAMPS
            // ================================================================
            $table->timestamps();

            // ================================================================
            // 5. ÍNDICES
            // ================================================================
            $table->index('code');
            $table->index('is_base');
            $table->index('is_default');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};