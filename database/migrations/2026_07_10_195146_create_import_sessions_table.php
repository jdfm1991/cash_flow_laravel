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
        Schema::create('import_sessions', function (Blueprint $table) {
            // ================================================================
            // 1. IDENTIFICADOR
            // ================================================================
            $table->id();

            // ================================================================
            // 2. RELACIONES
            // ================================================================
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete()
                ->comment('Empresa que realiza la importación');

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Usuario que realiza la importación');

            // ================================================================
            // 3. DATOS DE LA IMPORTACIÓN
            // ================================================================
            $table->enum('source', ['excel', 'migration', 'csv', 'pdf'])
                ->comment('Fuente de la importación');

            $table->string('file_name', 255)->nullable()
                ->comment('Nombre del archivo (si aplica)');

            $table->string('file_path', 255)->nullable()
                ->comment('Ruta del archivo (si aplica)');

            $table->integer('total_rows')->default(0)
                ->comment('Total de filas procesadas');

            $table->integer('processed_rows')->default(0)
                ->comment('Filas procesadas exitosamente');

            $table->integer('duplicated_rows')->default(0)
                ->comment('Filas duplicadas saltadas');

            $table->integer('error_rows')->default(0)
                ->comment('Filas con errores');

            // ================================================================
            // 4. ESTADO Y METADATOS
            // ================================================================
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending')
                ->comment('Estado de la importación');

            $table->json('error_log')->nullable()
                ->comment('Log de errores');

            $table->json('metadata')->nullable()
                ->comment('Metadatos adicionales');

            // ================================================================
            // 5. TIMESTAMPS
            // ================================================================
            $table->timestamp('started_at')->nullable()
                ->comment('Fecha de inicio de la importación');

            $table->timestamp('completed_at')->nullable()
                ->comment('Fecha de finalización');

            $table->timestamps();
            $table->softDeletes();

            // ================================================================
            // 6. ÍNDICES
            // ================================================================
            $table->index('company_id');
            $table->index('user_id');
            $table->index('source');
            $table->index('status');
            $table->index('started_at');
            $table->index('completed_at');
            $table->index(['status', 'source']);
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_sessions');
    }
};