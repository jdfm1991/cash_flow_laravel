<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            // ================================================================
            // 1. IDENTIFICADOR
            // ================================================================
            $table->id();

            // ================================================================
            // 2. INFORMACIÓN DE LA EMPRESA
            // ================================================================
            $table->string('name', 100);
            $table->string('business_name', 200)->nullable();
            $table->string('tax_id', 50)->nullable()->unique();
            $table->string('email', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('timezone', 50)->default('America/Caracas');

            // ================================================================
            // 3. SUSCRIPCIÓN
            // ================================================================
            $table->foreignId('subscription_plan_id')
                ->nullable()
                ->constrained('subscription_plans')
                ->nullOnDelete();

            $table->timestamp('subscription_expires_at')->nullable();

            // ================================================================
            // 4. ESTADO Y AUDITORÍA
            // ================================================================
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ================================================================
            // 5. TIMESTAMPS
            // ================================================================
            $table->timestamps();
            $table->softDeletes();

            // ================================================================
            // 6. ÍNDICES
            // ================================================================
            $table->index('tax_id');
            $table->index('is_active');
            $table->index('subscription_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};