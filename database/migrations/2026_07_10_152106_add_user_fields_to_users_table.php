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
        Schema::table('users', function (Blueprint $table) {
            // ================================================================
            // 1. CAMPOS DE PERFIL
            // ================================================================
            $table->string('avatar_path', 255)->nullable()->after('remember_token');

            // ================================================================
            // 2. CAMPOS DE ESTADO
            // ================================================================
            $table->boolean('is_active')->default(true)->after('avatar_path');

            // ================================================================
            // 3. CAMPOS DE SEGURIDAD Y AUDITORÍA
            // ================================================================
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->text('last_login_user_agent')->nullable()->after('last_login_ip');
            $table->integer('failed_login_attempts')->default(0)->after('last_login_user_agent');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');

            // ================================================================
            // 4. CONTEXTO DE EMPRESA (FK, se agregará después de crear companies)
            // ================================================================
            $table->foreignId('current_company_id')
                ->nullable()
                ->after('locked_until')
                ->constrained('companies')
                ->nullOnDelete();

            // ================================================================
            // 5. SOFT DELETE
            // ================================================================
            $table->softDeletes()->after('updated_at');

            // ================================================================
            // 6. ÍNDICES
            // ================================================================
            $table->index('is_active');
            $table->index('last_login_at');
            $table->index('current_company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Eliminar FK primero
            $table->dropForeign(['current_company_id']);

            // Eliminar columnas
            $table->dropColumn([
                'avatar_path',
                'is_active',
                'last_login_at',
                'last_login_ip',
                'last_login_user_agent',
                'failed_login_attempts',
                'locked_until',
                'current_company_id',
                'deleted_at',
            ]);
        });
    }
};