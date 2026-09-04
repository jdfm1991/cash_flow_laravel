<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ 1. Asegurar que la columna hash sea NOT NULL
        Schema::table('imported_transactions', function (Blueprint $table) {
            $table->string('hash', 32)->nullable(false)->change();
        });

        // ✅ 2. Generar hash para los registros existentes que tienen NULL
        $records = DB::table('imported_transactions')
            ->whereNull('hash')
            ->get();

        foreach ($records as $record) {
            $hashString = implode('|', [
                $record->company_id,
                $record->amount,
                $record->transaction_date,
                $record->reference ?? '',
                substr($record->description ?? '', 0, 50),
            ]);
            $hash = md5($hashString);

            DB::table('imported_transactions')
                ->where('id', $record->id)
                ->update(['hash' => $hash]);
        }

        // ✅ 3. Eliminar duplicados (mantener solo el primero)
        $duplicates = DB::table('imported_transactions')
            ->select('hash', DB::raw('MIN(id) as min_id'))
            ->groupBy('hash')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('imported_transactions')
                ->where('hash', $dup->hash)
                ->where('id', '>', $dup->min_id)
                ->delete();
        }
    }

    public function down(): void
    {
        Schema::table('imported_transactions', function (Blueprint $table) {
            $table->string('hash', 32)->nullable()->change();
        });
    }
};