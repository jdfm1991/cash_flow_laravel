<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('imported_transactions', function (Blueprint $table) {
            // ✅ 1. Renombrar original_currency a currency_id
            if (Schema::hasColumn('imported_transactions', 'original_currency')) {
                $table->renameColumn('original_currency', 'currency_id');
            }

            // ✅ 2. Cambiar el tipo de currency_id
            if (Schema::hasColumn('imported_transactions', 'currency_id')) {
                // Cambiar a unsignedBigInteger
                DB::statement('ALTER TABLE imported_transactions MODIFY currency_id BIGINT UNSIGNED NULL');
            }

            // ✅ 3. Renombrar original_amount a amount_converted
            if (Schema::hasColumn('imported_transactions', 'original_amount')) {
                $table->renameColumn('original_amount', 'amount_converted');
            }

            // ✅ 4. Agregar FK solo si existe la tabla currencies
            if (Schema::hasTable('currencies') && Schema::hasColumn('imported_transactions', 'currency_id')) {
                // Verificar si la FK ya existe antes de crearla
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                    WHERE TABLE_NAME = 'imported_transactions' 
                    AND COLUMN_NAME = 'currency_id' 
                    AND CONSTRAINT_NAME != 'PRIMARY'
                ");
                
                if (empty($foreignKeys)) {
                    $table->foreign('currency_id')
                        ->references('id')
                        ->on('currencies')
                        ->onDelete('set null');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imported_transactions', function (Blueprint $table) {
            // ✅ Eliminar FK
            if (Schema::hasTable('currencies')) {
                $table->dropForeign(['currency_id']);
            }

            // ✅ Revertir nombres
            if (Schema::hasColumn('imported_transactions', 'currency_id')) {
                $table->renameColumn('currency_id', 'original_currency');
            }

            if (Schema::hasColumn('imported_transactions', 'amount_converted')) {
                $table->renameColumn('amount_converted', 'original_amount');
            }

            // ✅ Revertir tipo de original_currency
            if (Schema::hasColumn('imported_transactions', 'original_currency')) {
                DB::statement('ALTER TABLE imported_transactions MODIFY original_currency VARCHAR(3) NULL');
            }
        });
    }
};