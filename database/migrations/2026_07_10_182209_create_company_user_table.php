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
        Schema::create('company_user', function (Blueprint $table) {
            // ================================================================
            // 1. IDENTIFICADOR
            // ================================================================
            $table->id();

            // ================================================================
            // 2. CLAVES FORÁNEAS
            // ================================================================
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // ================================================================
            // 3. CAMPOS DE LA RELACIÓN
            // ================================================================
            $table->boolean('is_default')->default(false)
                ->comment('Indica si esta es la empresa por defecto del usuario');

            $table->timestamp('joined_at')->nullable()
                ->comment('Fecha en que el usuario se unió a la empresa');

            $table->timestamp('left_at')->nullable()
                ->comment('Fecha en que el usuario dejó la empresa');

            // ================================================================
            // 4. TIMESTAMPS
            // ================================================================
            $table->timestamps();

            // ================================================================
            // 5. ÍNDICES Y RESTRICCIONES
            // ================================================================
            $table->unique(['user_id', 'company_id'])
                ->comment('Un usuario no puede estar dos veces en la misma empresa');

            $table->index('is_default');
            $table->index('joined_at');
            $table->index('left_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};