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
        Schema::create('categories', function (Blueprint $table) {
            // ================================================================
            // 1. IDENTIFICADOR
            // ================================================================
            $table->id();

            // ================================================================
            // 2. JERARQUÍA (Auto-referencia)
            // ================================================================
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->cascadeOnDelete()
                ->comment('Categoría padre (null = raíz)');

            // ================================================================
            // 3. DATOS DE LA CATEGORÍA
            // ================================================================
            $table->string('name', 50)
                ->comment('Nombre de la categoría');

            $table->enum('type', ['income', 'expense', 'asset', 'liability', 'equity'])
                ->default('expense')
                ->comment('Tipo de categoría');

            $table->string('code', 20)->nullable()
                ->comment('Código contable (ej: 4.1.1)');

            $table->string('codigo_contable', 20)->nullable()
                ->comment('Código en el sistema contable externo');

            $table->bigInteger('cuenta_contable_id')->nullable()
                ->comment('ID en el sistema contable externo');

            // ================================================================
            // 4. DATOS VISUALES
            // ================================================================
            $table->string('icon', 50)->default('bi-tag')
                ->comment('Icono para la UI (Bootstrap Icons)');

            $table->string('color', 7)->default('#6c757d')
                ->comment('Color en formato hexadecimal');

            $table->text('description')->nullable()
                ->comment('Descripción de la categoría');

            // ================================================================
            // 5. ESTADO Y ORDEN
            // ================================================================
            $table->boolean('is_system')->default(false)
                ->comment('Categoría del sistema (no editable)');

            $table->boolean('is_active')->default(true)
                ->comment('Categoría activa');

            $table->integer('sort_order')->default(0)
                ->comment('Orden de visualización');

            // ================================================================
            // 6. TIMESTAMPS
            // ================================================================
            $table->timestamps();

            // ================================================================
            // 7. ÍNDICES Y RESTRICCIONES
            // ================================================================
            // Una categoría no puede tener el mismo nombre dentro del mismo tipo y padre
            $table->unique(['name', 'type', 'parent_id'])
                ->comment('Nombre único dentro del mismo tipo y padre');

            $table->index('parent_id');
            $table->index('type');
            $table->index('is_active');
            $table->index('sort_order');
            $table->index('code');
            $table->index('codigo_contable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};