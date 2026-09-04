<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Company;

class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@cashflow.com')->first();
        $company = Company::first();

        if (!$admin || !$company) {
            $this->command->error('❌ Usuario o empresa no encontrados');
            return;
        }

        $logs = [
            [
                'user_id' => $admin->id,
                'company_id' => $company->id,
                'action' => AuditLog::ACTION_LOGIN,
                'entity_type' => 'User',
                'entity_id' => $admin->id,
                'metadata' => [
                    'ip' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                ],
                'created_at' => now()->subHours(2),
            ],
            [
                'user_id' => $admin->id,
                'company_id' => $company->id,
                'action' => AuditLog::ACTION_CREATE,
                'entity_type' => 'Company',
                'entity_id' => $company->id,
                'new_data' => ['name' => $company->name, 'tax_id' => $company->tax_id],
                'created_at' => now()->subHours(3),
            ],
        ];

        foreach ($logs as $log) {
            AuditLog::create($log);
        }

        $this->command->info('✅ Logs de auditoría creados');
    }
}