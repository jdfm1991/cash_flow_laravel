<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // ================================================================
            // 1. RENOMBRAR CAMPOS
            // ================================================================

            if (Schema::hasColumn('companies', 'logo') &&
                !Schema::hasColumn('companies', 'logo_path')) {
                $table->renameColumn('logo', 'logo_path');
            }

            if (Schema::hasColumn('companies', 'subscription_plan')) {
                $table->renameColumn('subscription_plan', 'subscription_plan_id');
                $table->unsignedBigInteger('subscription_plan_id')->nullable()->change();
            }

            // ================================================================
            // 2. AGREGAR NUEVOS CAMPOS
            // ================================================================

            if (!Schema::hasColumn('companies', 'timezone')) {
                $table->string('timezone', 50)->default('America/Caracas')->after('theme');
            }

            if (!Schema::hasColumn('companies', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('is_active');
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            }

            // ================================================================
            // 3. SOFT DELETE (si no existe)
            // ================================================================

            if (!Schema::hasColumn('companies', 'deleted_at')) {
                $table->softDeletes();
            }

            // ================================================================
            // 4. FK (si no existe)
            // ================================================================

            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_NAME = 'companies' 
                AND COLUMN_NAME = 'subscription_plan_id' 
                AND REFERENCED_TABLE_NAME = 'subscription_plans'
            ");

            if (empty($foreignKeys)) {
                $table->foreign('subscription_plan_id')
                    ->references('id')
                    ->on('subscription_plans')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['subscription_plan_id']);

            $table->renameColumn('logo_path', 'logo');
            $table->renameColumn('subscription_plan_id', 'subscription_plan');

            $table->dropColumn(['timezone', 'created_by']);
            $table->dropSoftDeletes();
        });
    }
};