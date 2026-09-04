<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ImportSession;
use App\Models\User;
use App\Models\Company;

class ImportSessionSeeder extends Seeder
{
    public function run(): void
    {
        // ================================================================
        // 1. OBTENER DATOS NECESARIOS
        // ================================================================

        $admin = User::where('email', 'admin@cashflow.com')->first();
        $company = Company::first();

        if (!$admin || !$company) {
            $this->command->error('❌ Usuario o empresa no encontrados');
            return;
        }

        // ================================================================
        // 2. DEFINIR SESIONES DE IMPORTACIÓN
        // ================================================================

        $sessions = [
            // ============================================================
            // SESIÓN COMPLETADA (Excel)
            // ============================================================
            [
                'company_id' => $company->id,
                'user_id' => $admin->id,
                'source' => 'excel',
                'file_name' => 'transacciones_enero_2026.xlsx',
                'file_path' => 'imports/transacciones_enero_2026.xlsx',
                'total_rows' => 150,
                'processed_rows' => 145,
                'duplicated_rows' => 3,
                'error_rows' => 2,
                'status' => 'completed',
                'error_log' => [
                    ['row' => 12, 'error' => 'Monto inválido'],
                    ['row' => 45, 'error' => 'Cuenta no encontrada'],
                ],
                'metadata' => [
                    'bank_account_id' => 1,
                    'date_range' => ['2026-01-01', '2026-01-31'],
                ],
                'started_at' => now()->subDays(10),
                'completed_at' => now()->subDays(10)->addMinutes(5),
            ],

            // ============================================================
            // SESIÓN COMPLETADA (Migración)
            // ============================================================
            [
                'company_id' => $company->id,
                'user_id' => $admin->id,
                'source' => 'migration',
                'file_name' => null,
                'file_path' => null,
                'total_rows' => 500,
                'processed_rows' => 498,
                'duplicated_rows' => 0,
                'error_rows' => 2,
                'status' => 'completed',
                'error_log' => [
                    ['row' => 230, 'error' => 'Tasa de cambio no encontrada'],
                    ['row' => 340, 'error' => 'Moneda no válida'],
                ],
                'metadata' => [
                    'connection_id' => 2,
                    'source_db' => 'sparrow_siadcli',
                ],
                'started_at' => now()->subDays(5),
                'completed_at' => now()->subDays(5)->addMinutes(15),
            ],

            // ============================================================
            // SESIÓN EN PROCESO
            // ============================================================
            [
                'company_id' => $company->id,
                'user_id' => $admin->id,
                'source' => 'excel',
                'file_name' => 'transacciones_febrero_2026.xlsx',
                'file_path' => 'imports/transacciones_febrero_2026.xlsx',
                'total_rows' => 200,
                'processed_rows' => 100,
                'duplicated_rows' => 0,
                'error_rows' => 0,
                'status' => 'processing',
                'error_log' => null,
                'metadata' => [
                    'bank_account_id' => 1,
                    'date_range' => ['2026-02-01', '2026-02-28'],
                ],
                'started_at' => now()->subMinutes(30),
                'completed_at' => null,
            ],

            // ============================================================
            // SESIÓN FALLIDA
            // ============================================================
            [
                'company_id' => $company->id,
                'user_id' => $admin->id,
                'source' => 'csv',
                'file_name' => 'transacciones_marzo_2026.csv',
                'file_path' => 'imports/transacciones_marzo_2026.csv',
                'total_rows' => 50,
                'processed_rows' => 0,
                'duplicated_rows' => 0,
                'error_rows' => 50,
                'status' => 'failed',
                'error_log' => [
                    ['row' => 1, 'error' => 'Formato de archivo inválido'],
                    ['row' => 2, 'error' => 'Cabeceras no coinciden'],
                ],
                'metadata' => [
                    'error_type' => 'parse_error',
                ],
                'started_at' => now()->subDays(2),
                'completed_at' => now()->subDays(2)->addMinutes(1),
            ],

            // ============================================================
            // SESIÓN PENDIENTE
            // ============================================================
            [
                'company_id' => $company->id,
                'user_id' => $admin->id,
                'source' => 'pdf',
                'file_name' => 'extracto_bancario_abril_2026.pdf',
                'file_path' => 'imports/extracto_bancario_abril_2026.pdf',
                'total_rows' => 0,
                'processed_rows' => 0,
                'duplicated_rows' => 0,
                'error_rows' => 0,
                'status' => 'pending',
                'error_log' => null,
                'metadata' => null,
                'started_at' => null,
                'completed_at' => null,
            ],
        ];

        // ================================================================
        // 3. INSERTAR SESIONES
        // ================================================================

        foreach ($sessions as $session) {
            ImportSession::create($session);
        }

        // ================================================================
        // 4. VERIFICACIÓN
        // ================================================================

        $this->command->info('✅ Sesiones de importación creadas:');
        $this->command->table(
            ['ID', 'Fuente', 'Archivo', 'Estado', 'Filas', 'Procesadas'],
            ImportSession::with(['company', 'user'])
                ->get()
                ->map(fn($session) => [
                    $session->id,
                    $session->source_label,
                    $session->file_name ?? 'N/A',
                    $session->status_label,
                    $session->total_rows,
                    $session->processed_rows,
                ])
                ->toArray()
        );
    }
}