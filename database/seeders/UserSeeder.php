<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Crear usuario super_admin
        $user = User::firstOrCreate(
            ['email' => 'admin@cashflow.com'],
            [
                'name' => 'Super Admin',
                'username' => 'admin',
                'password' => Hash::make('20975144'),
                'is_active' => true,
                'email_verified' => true,
            ]
        );

        // ✅ Asignar empresa si existe
        $company = Company::first();
        if ($company) {
            $user->companies()->syncWithoutDetaching([$company->id => ['is_default' => true]]);
            $user->update(['current_company_id' => $company->id]);
        }

        // ✅ Asignar rol super_admin (asegurar que existe)
        if ($user) {
            $user->assignRole('super_admin');
        }

        $this->command->info('Usuario admin creado: admin@cashflow.com / 20975144');
    }
}