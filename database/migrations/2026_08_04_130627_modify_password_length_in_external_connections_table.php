<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_connections', function (Blueprint $table) {
            // ✅ Cambiar a TEXT para soportar strings largos
            $table->text('password')->change();
        });
    }

    public function down(): void
    {
        Schema::table('external_connections', function (Blueprint $table) {
            // Revertir a string(255)
            $table->string('password', 255)->change();
        });
    }
};