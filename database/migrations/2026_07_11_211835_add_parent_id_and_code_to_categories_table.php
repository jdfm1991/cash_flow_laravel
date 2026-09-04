<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // ================================================================
            // 1. AGREGAR NUEVOS CAMPOS
            // ================================================================

            // parent_id (jerarquía)
            if (!Schema::hasColumn('categories', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('categories')
                    ->nullOnDelete();
            }

            // code (código contable)
            if (!Schema::hasColumn('categories', 'code')) {
                $table->string('code', 20)->nullable()->after('type');
            }

            // codigo_contable (integración con sistema contable)
            if (!Schema::hasColumn('categories', 'codigo_contable')) {
                $table->string('codigo_contable', 20)->nullable()->after('code');
            }

            // cuenta_contable_id (ID en sistema contable externo)
            if (!Schema::hasColumn('categories', 'cuenta_contable_id')) {
                $table->bigInteger('cuenta_contable_id')->nullable()->after('codigo_contable');
            }

            // ================================================================
            // 2. ÍNDICES
            // ================================================================

            // parent_id
            if (!Schema::hasColumn('categories', 'parent_id')) {
                $table->index('parent_id');
            }

            // code
            if (!Schema::hasColumn('categories', 'code')) {
                $table->index('code');
            }

            // codigo_contable
            if (!Schema::hasColumn('categories', 'codigo_contable')) {
                $table->index('codigo_contable');
            }
        });

        // ================================================================
        // 3. ELIMINAR ÍNDICE UNIQUE ANTIGUO (SI EXISTE)
        // ================================================================

        // Verificar qué índices existen en la tabla
        $indexes = DB::select("
            SHOW INDEX FROM categories
        ");

        // Buscar el índice unique que contiene name y type
        $uniqueIndexToDrop = null;
        foreach ($indexes as $index) {
            // Buscar índices que contengan 'name' y 'type'
            if (strpos($index->Key_name, 'name') !== false && 
                strpos($index->Key_name, 'type') !== false && 
                $index->Non_unique == 0) {
                $uniqueIndexToDrop = $index->Key_name;
                break;
            }
        }

        // Si encontramos el índice, lo eliminamos
        if ($uniqueIndexToDrop) {
            DB::statement("ALTER TABLE categories DROP INDEX {$uniqueIndexToDrop}");
        }

        // ================================================================
        // 4. CREAR NUEVO ÍNDICE UNIQUE
        // ================================================================

        // Verificar que no exista ya
        $newIndexExists = false;
        foreach ($indexes as $index) {
            if ($index->Key_name === 'categories_name_type_parent_id_unique') {
                $newIndexExists = true;
                break;
            }
        }

        if (!$newIndexExists) {
            DB::statement("
                ALTER TABLE categories 
                ADD UNIQUE INDEX categories_name_type_parent_id_unique (name, type, parent_id)
            ");
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Eliminar índices
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['code']);
            $table->dropIndex(['codigo_contable']);

            // Eliminar unique
            $table->dropUnique('categories_name_type_parent_id_unique');

            // Eliminar campos
            $table->dropForeign(['parent_id']);
            $table->dropColumn([
                'parent_id',
                'code',
                'codigo_contable',
                'cuenta_contable_id',
            ]);
        });
    }
};