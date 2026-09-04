<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('guard_name')
                ->comment('Indica si el rol es del sistema (no editable/eliminable)');
            
            $table->string('description')->nullable()->after('is_system')
                ->comment('Descripción del rol');
        });

        // ✅ Marcar roles existentes como sistema
        DB::table('roles')->whereIn('name', ['super_admin', 'admin', 'user', 'accountant', 'viewer'])
            ->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['is_system', 'description']);
        });
    }
};