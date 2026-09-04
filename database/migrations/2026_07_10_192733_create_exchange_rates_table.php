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
        Schema::create('exchange_rates', function (Blueprint $table) {
            // ================================================================
            // 1. IDENTIFICADOR
            // ================================================================
            $table->id();

            // ================================================================
            // 2. RELACIONES CON MONEDAS
            // ================================================================
            $table->foreignId('from_currency_id')
                ->constrained('currencies')
                ->cascadeOnDelete()
                ->comment('Moneda origen');

            $table->foreignId('to_currency_id')
                ->constrained('currencies')
                ->cascadeOnDelete()
                ->comment('Moneda destino');

            // ================================================================
            // 3. DATOS DE LA TASA
            // ================================================================
            $table->decimal('rate', 20, 8)
                ->comment('Tasa de cambio (ej: 517.96000000)');

            $table->date('effective_date')
                ->comment('Fecha efectiva de la tasa');

            $table->enum('source', ['manual', 'api', 'system'])
                ->default('manual')
                ->comment('Fuente de la tasa');

            $table->text('notes')->nullable()
                ->comment('Notas adicionales');

            // ================================================================
            // 4. AUDITORÍA
            // ================================================================
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Usuario que registró la tasa');

            // ================================================================
            // 5. ESTADO
            // ================================================================
            $table->boolean('is_current')->default(true)
                ->comment('Tasa más reciente para el par de monedas');

            // ================================================================
            // 6. TIMESTAMPS
            // ================================================================
            $table->timestamps();

            // ================================================================
            // 7. ÍNDICES Y RESTRICCIONES
            // ================================================================
            // ✅ CORREGIDO: Nombre de índice más corto
            $table->unique(
                ['from_currency_id', 'to_currency_id', 'effective_date'],
                'exrates_pair_date_unique'  // ← Nombre corto!
            )->comment('Tasa única por par de monedas y fecha');

            $table->index('from_currency_id');
            $table->index('to_currency_id');
            $table->index('effective_date');
            $table->index('is_current');
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};