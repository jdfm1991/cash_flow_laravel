<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            // ================================================================
            // 1. IDENTIFICADOR
            // ================================================================
            $table->id();

            // ================================================================
            // 2. RELACIÓN CON CATEGORÍA
            // ================================================================
            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete()
                ->comment('Categoría a la que pertenece la cuenta');

            // ================================================================
            // 3. DATOS DE LA CUENTA
            // ================================================================
            $table->string('name', 100)
                ->comment('Nombre de la cuenta contable');

            $table->text('description')->nullable()
                ->comment('Descripción de la cuenta');

            // ================================================================
            // 4. INTEGRACIÓN CONTABLE
            // ================================================================
            $table->string('codigo_contable', 20)->nullable()
                ->comment('Código en el sistema contable externo');

            $table->bigInteger('cuenta_contable_id')->nullable()
                ->comment('ID en el sistema contable externo');

            // ================================================================
            // 5. ESTADO Y ORDEN
            // ================================================================
            $table->boolean('is_system')->default(false)
                ->comment('Cuenta del sistema (no editable)');

            $table->boolean('is_active')->default(true)
                ->comment('Cuenta activa');

            $table->integer('sort_order')->default(0)
                ->comment('Orden de visualización');

            // ================================================================
            // 6. TIMESTAMPS
            // ================================================================
            $table->timestamps();

            // ================================================================
            // 7. ÍNDICES Y RESTRICCIONES
            // ================================================================
            $table->unique(['name', 'category_id'])
                ->comment('Nombre único dentro de la misma categoría');

            $table->index('category_id');
            $table->index('is_active');
            $table->index('sort_order');
            $table->index('codigo_contable');
            $table->index('cuenta_contable_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};