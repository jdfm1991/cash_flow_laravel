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
        Schema::create('audit_logs', function (Blueprint $table) {
            // ================================================================
            // 1. IDENTIFICADOR
            // ================================================================
            $table->id();

            // ================================================================
            // 2. RELACIONES
            // ================================================================
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Usuario que realizó la acción');

            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete()
                ->comment('Empresa en contexto');

            // ================================================================
            // 3. DATOS DE LA ACCIÓN
            // ================================================================
            $table->string('action', 100)
                ->comment('Acción realizada (login, create, update, delete, etc.)');

            $table->string('entity_type', 50)->nullable()
                ->comment('Tipo de entidad afectada (User, Company, Transaction, etc.)');

            $table->bigInteger('entity_id')->nullable()
                ->comment('ID de la entidad afectada');

            // ================================================================
            // 4. DATOS ANTES/DESPUÉS
            // ================================================================
            $table->json('old_data')->nullable()
                ->comment('Datos antes del cambio (para updates y deletes)');

            $table->json('new_data')->nullable()
                ->comment('Datos después del cambio (para creates y updates)');

            $table->json('metadata')->nullable()
                ->comment('Metadatos adicionales (IP, user_agent, etc.)');

            // ================================================================
            // 5. TIMESTAMPS
            // ================================================================
            $table->timestamp('created_at')->useCurrent()
                ->comment('Fecha y hora de la acción');

            // ================================================================
            // 6. ÍNDICES
            // ================================================================
            $table->index('user_id');
            $table->index('company_id');
            $table->index('action');
            $table->index('entity_type');
            $table->index('entity_id');
            $table->index('created_at');

            // Índices compuestos
            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['company_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};