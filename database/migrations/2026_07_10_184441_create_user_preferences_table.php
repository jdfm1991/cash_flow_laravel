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
        Schema::create('user_preferences', function (Blueprint $table) {
            // ================================================================
            // 1. IDENTIFICADOR
            // ================================================================
            $table->id();

            // ================================================================
            // 2. RELACIÓN CON USUARIO (1:1)
            // ================================================================
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->unique()  // Un usuario solo tiene una fila de preferencias
                ->comment('Relación 1:1 con el usuario');

            // ================================================================
            // 3. PREFERENCIAS DE IDIOMA Y REGIÓN
            // ================================================================
            $table->string('language', 10)->default('es')
                ->comment('Idioma: es, en, pt, etc.');

            $table->string('timezone', 50)->default('America/Caracas')
                ->comment('Zona horaria del usuario');

            // ================================================================
            // 4. PREFERENCIAS DE VISUALIZACIÓN
            // ================================================================
            $table->string('theme', 20)->default('light')
                ->comment('Tema: light, dark, system');

            $table->string('date_format', 20)->default('Y-m-d')
                ->comment('Formato de fecha: Y-m-d, d/m/Y, m/d/Y');

            $table->string('time_format', 10)->default('H:i')
                ->comment('Formato de hora: H:i, h:i A');

            $table->string('currency_display', 20)->default('symbol')
                ->comment('Mostrar moneda: symbol, code, both');

            // ================================================================
            // 5. PREFERENCIAS DE NOTIFICACIONES
            // ================================================================
            $table->boolean('notifications_email')->default(true)
                ->comment('Recibir notificaciones por email');

            $table->boolean('notifications_push')->default(true)
                ->comment('Recibir notificaciones push');

            $table->boolean('notifications_in_app')->default(true)
                ->comment('Recibir notificaciones dentro de la app');

            // ================================================================
            // 6. PREFERENCIAS DEL DASHBOARD
            // ================================================================
            $table->json('dashboard_layout')->nullable()
                ->comment('JSON con la disposición de widgets del dashboard');

            $table->json('dashboard_widgets')->nullable()
                ->comment('JSON con los widgets activos/ordenados');

            // ================================================================
            // 7. OTRAS PREFERENCIAS
            // ================================================================
            $table->integer('items_per_page')->default(25)
                ->comment('Cantidad de items por página en listados');

            $table->boolean('auto_save_reports')->default(false)
                ->comment('Guardar automáticamente los reportes');

            $table->json('favorite_reports')->nullable()
                ->comment('JSON con IDs de reportes favoritos');

            // ================================================================
            // 8. TIMESTAMPS
            // ================================================================
            $table->timestamps();

            // ================================================================
            // 9. ÍNDICES
            // ================================================================
            $table->index('user_id');
            $table->index('language');
            $table->index('theme');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};