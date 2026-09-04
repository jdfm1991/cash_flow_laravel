<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imported_transactions', function (Blueprint $table) {
            $table->id();
            
            // Relaciones principales
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();
            
            $table->foreignId('bank_id')
                ->nullable()
                ->constrained('banks')
                ->nullOnDelete();
            
            $table->string('bank_name', 100)->nullable();
            
            $table->foreignId('bank_account_id')
                ->nullable()
                ->constrained('bank_accounts')
                ->nullOnDelete();
            
            // Datos de la transacción importada
            $table->date('transaction_date');
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 4);
            
            $table->enum('transaction_type', ['income', 'expense'])
                ->default('income');
            
            // Datos originales (para auditoría)
            $table->decimal('original_amount', 15, 4)->nullable();
            $table->string('original_currency', 3)->nullable();
            $table->decimal('exchange_rate', 20, 8)->nullable();
            
            // Estado de procesamiento
            $table->boolean('is_processed')->default(false);
            $table->foreignId('mapped_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->string('mapped_category', 50)->nullable();
            $table->text('error_log')->nullable();
            
            // Sesión de importación
            $table->string('import_session_id', 100);
            $table->string('hash', 32)->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Índices
            $table->index('company_id');
            $table->index('bank_id');
            $table->index('bank_account_id');
            $table->index('transaction_date');
            $table->index('is_processed');
            $table->index('import_session_id');
            $table->unique(['company_id', 'hash'], 'imported_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_transactions');
    }
};