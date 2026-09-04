<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            // ================================================================
            // 1. IDENTIFICADOR
            // ================================================================
            $table->id();

            // ================================================================
            // 2. INFORMACIÓN BÁSICA DEL PLAN
            // ================================================================
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();

            // ================================================================
            // 3. LÍMITES DEL PLAN
            // ================================================================
            $table->integer('max_users')->default(5);
            $table->integer('max_bank_accounts')->default(50);
            $table->integer('max_transactions_per_month')->default(500);

            // ================================================================
            // 4. FUNCIONALIDADES Y PRECIO
            // ================================================================
            $table->json('features')->nullable();
            $table->decimal('price', 10, 2)->default(0.00);

            // 👇🏻 AHORA SÍ, currencies YA EXISTE
            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            // ================================================================
            // 5. ESTADO Y ORDEN
            // ================================================================
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            // ================================================================
            // 6. TIMESTAMPS
            // ================================================================
            $table->timestamps();

            // ================================================================
            // 7. ÍNDICES
            // ================================================================
            $table->index('slug');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};