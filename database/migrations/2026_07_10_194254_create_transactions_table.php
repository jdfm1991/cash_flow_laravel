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
        Schema::create('transactions', function (Blueprint $table) {
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
                ->comment('Empresa');

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Usuario que registró');

            $table->foreignId('bank_account_id')
                ->nullable()
                ->constrained('bank_accounts')
                ->nullOnDelete()
                ->comment('Cuenta bancaria asociada');

            // ================================================================
            // 3. CONTABILIDAD
            // ================================================================
            $table->foreignId('account_id')
                ->constrained('accounts')
                ->cascadeOnDelete()
                ->comment('Cuenta contable');

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete()
                ->comment('Categoría (denormalizado)');

            // ================================================================
            // 4. TIPO Y MONEDA
            // ================================================================
            $table->enum('type', ['income', 'expense', 'transfer'])
                ->comment('Tipo de transacción');

            $table->decimal('amount', 15, 4)
                ->comment('Monto en moneda original');

            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->cascadeOnDelete()
                ->comment('Moneda de la transacción');

            $table->foreignId('exchange_rate_id')
                ->nullable()
                ->constrained('exchange_rates')
                ->nullOnDelete()
                ->comment('Tasa usada en la conversión');

            $table->decimal('exchange_rate', 20, 8)->nullable()
                ->comment('Copia de la tasa (auditoría)');

            $table->decimal('amount_converted', 15, 4)
                ->comment('Monto convertido a moneda base');

            // ================================================================
            // 5. DATOS DE LA TRANSACCIÓN
            // ================================================================
            $table->date('date')
                ->comment('Fecha de la transacción');

            $table->text('description')->nullable()
                ->comment('Descripción');

            $table->string('reference', 100)->nullable()
                ->comment('Referencia externa');

            $table->enum('payment_method', ['cash', 'bank', 'transfer'])
                ->default('bank')
                ->comment('Método de pago');

            $table->string('receipt_path', 255)->nullable()
                ->comment('Comprobante');

            // ================================================================
            // 6. TRANSFERENCIAS (self-reference)
            // ================================================================
            $table->foreignId('transfer_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete()
                ->comment('Si es transferencia, referencia a la transacción vinculada');

            // ================================================================
            // 7. ANTI-DUPLICACIÓN
            // ================================================================
            $table->string('hash', 32)->unique()
                ->comment('Hash para evitar duplicados');

            // ================================================================
            // 8. CONCILIACIÓN
            // ================================================================
            $table->boolean('is_reconciled')->default(false)
                ->comment('Conciliado con extracto bancario');

            $table->timestamp('reconciled_at')->nullable()
                ->comment('Fecha de conciliación');

            $table->foreignId('reconciled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Usuario que concilió');

            // ================================================================
            // 9. CONTABILIZACIÓN
            // ================================================================
            $table->bigInteger('asiento_contable_id')->nullable()
                ->comment('ID del asiento en el sistema contable');

            $table->boolean('contabilizada')->default(false)
                ->comment('Generó asiento contable');

            // ================================================================
            // 10. IMPORTACIÓN
            // ================================================================
            $table->string('import_session_id', 100)->nullable()
                ->comment('Sesión de importación');

            $table->enum('source', ['manual', 'excel', 'migration', 'api'])
                ->default('manual')
                ->comment('Fuente de la transacción');

            // ================================================================
            // 11. TIMESTAMPS Y SOFT DELETE
            // ================================================================
            $table->timestamps();
            $table->softDeletes();

            // ================================================================
            // 12. ÍNDICES
            // ================================================================
            $table->index('company_id');
            $table->index('user_id');
            $table->index('bank_account_id');
            $table->index('account_id');
            $table->index('category_id');
            $table->index('currency_id');
            $table->index('exchange_rate_id');
            $table->index('date');
            $table->index('type');
            $table->index('payment_method');
            $table->index('is_reconciled');
            $table->index('contabilizada');
            $table->index('source');
            $table->index('transfer_id');

            // Índices compuestos para consultas comunes
            $table->index(['company_id', 'date', 'type']);
            $table->index(['company_id', 'category_id', 'date']);
            $table->index(['bank_account_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};