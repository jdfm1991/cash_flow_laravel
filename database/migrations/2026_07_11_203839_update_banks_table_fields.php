<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            // ================================================================
            // 1. RENOMBRAR CAMPOS (SI EXISTEN)
            // ================================================================

            if (Schema::hasColumn('banks', 'country') && 
                !Schema::hasColumn('banks', 'country_code')) {
                $table->renameColumn('country', 'country_code');
            }

            if (Schema::hasColumn('banks', 'logo') && 
                !Schema::hasColumn('banks', 'logo_path')) {
                $table->renameColumn('logo', 'logo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            if (Schema::hasColumn('banks', 'country_code') && 
                !Schema::hasColumn('banks', 'country')) {
                $table->renameColumn('country_code', 'country');
            }

            if (Schema::hasColumn('banks', 'logo_path') && 
                !Schema::hasColumn('banks', 'logo')) {
                $table->renameColumn('logo_path', 'logo');
            }
        });
    }
};