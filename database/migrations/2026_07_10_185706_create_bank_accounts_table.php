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
        Schema::create('bank_accounts', function (Blueprint $table) {
            // ================================================================
            // 1. IDENTIFICADOR
            // ================================================================
            $table->id();

            // ================================================================
            // 2. RELACIONES PRINCIPALES
            // ================================================================
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete()
                ->comment('Empresa propietaria de la cuenta');

            $table->foreignId('bank_id')
                ->constrained('banks')
                ->cascadeOnDelete()
                ->comment('Banco del catálogo global');

            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->cascadeOnDelete()
                ->comment('Moneda de la cuenta');

            // ================================================================
            // 3. DATOS DE LA CUENTA
            // ================================================================
            $table->string('account_number', 50)
                ->comment('Número de cuenta bancaria');

            $table->string('alias', 100)
                ->comment('Nombre descriptivo (ej: Cuenta Principal)');

            $table->enum('account_type', [
                'corriente',
                'ahorros',
                'nomina',
                'inversion',
                'caja_chica',
                'efectivo',
                'tarjeta_credito',
                'virtual',
            ])->default('corriente')
                ->comment('Tipo de cuenta');

            // ================================================================
            // 4. DATOS ADICIONALES
            // ================================================================
            $table->string('account_holder', 100)->nullable()
                ->comment('Titular de la cuenta (si es diferente a la empresa)');

            $table->decimal('opening_balance', 15, 4)->default(0)
                ->comment('Saldo inicial de la cuenta');

            $table->date('opened_at')->nullable()
                ->comment('Fecha de apertura de la cuenta');

            $table->text('notes')->nullable()
                ->comment('Notas adicionales sobre la cuenta');

            // ================================================================
            // 5. ESTADO Y CONFIGURACIÓN
            // ================================================================
            $table->boolean('is_active')->default(true)
                ->comment('Cuenta activa para operaciones');

            $table->boolean('is_default')->default(false)
                ->comment('Cuenta por defecto para la empresa');

            $table->json('metadata')->nullable()
                ->comment('Metadatos adicionales (CBU, CLABE, IBAN, etc.)');

            // ================================================================
            // 6. TIMESTAMPS
            // ================================================================
            $table->timestamps();

            // ================================================================
            // 7. ÍNDICES Y RESTRICCIONES
            // ================================================================
            // Una empresa no puede tener dos cuentas con el mismo número
            $table->unique(['company_id', 'account_number'])
                ->comment('Número de cuenta único por empresa');

            $table->index('account_type');
            $table->index('is_active');
            $table->index('is_default');
            $table->index('currency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};