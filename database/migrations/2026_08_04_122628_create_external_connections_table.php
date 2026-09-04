<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_connections', function (Blueprint $table) {
            // ================================================================
            // 1. IDENTIFICADOR
            // ================================================================
            $table->id();

            // ================================================================
            // 2. RELACIÓN CON EMPRESA
            // ================================================================
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete()
                ->comment('Empresa propietaria de la conexión');

            // ================================================================
            // 3. INFORMACIÓN DE LA CONEXIÓN
            // ================================================================
            $table->string('name', 100)
                ->comment('Nombre descriptivo de la conexión');

            $table->enum('type', ['migration', 'replication', 'integration'])
                ->default('migration')
                ->comment('Tipo de conexión');

            // ================================================================
            // 4. DATOS DE LA BASE DE DATOS EXTERNA
            // ================================================================
            $table->string('host', 255)
                ->comment('Host de la base de datos externa');

            $table->integer('port')
                ->default(3306)
                ->comment('Puerto de la base de datos externa');

            $table->string('db_name', 100)
                ->comment('Nombre de la base de datos externa');

            $table->string('username', 100)
                ->comment('Usuario de la base de datos externa');

            $table->string('password', 255)
                ->comment('Contraseña encriptada de la base de datos externa');

            // ================================================================
            // 5. CONFIGURACIÓN DE LA TABLA EXTERNA
            // ================================================================
            $table->string('table_name', 100)
                ->comment('Nombre de la tabla en la base de datos externa');

            $table->json('field_mapping')
                ->nullable()
                ->comment('Mapeo de campos entre el sistema externo y el nuestro');

            $table->text('query_template')
                ->nullable()
                ->comment('SQL personalizado para la migración');

            // ================================================================
            // 6. SEGUIMIENTO DE SINCRONIZACIÓN
            // ================================================================
            $table->timestamp('last_sync_at')
                ->nullable()
                ->comment('Última fecha de sincronización');

            // ================================================================
            // 7. ESTADO
            // ================================================================
            $table->boolean('is_active')
                ->default(true)
                ->comment('Conexión activa');

            // ================================================================
            // 8. TIMESTAMPS
            // ================================================================
            $table->timestamps();

            // ================================================================
            // 9. ÍNDICES
            // ================================================================
            $table->index('company_id');
            $table->index('type');
            $table->index('is_active');
            $table->index('host');
            $table->index('db_name');

            // Índice compuesto para búsquedas frecuentes
            $table->index(['company_id', 'is_active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_connections');
    }
};