<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\SubscriptionPlan;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        // ================================================================
        // 1. OBTENER EL PLAN FREE
        // ================================================================
        $freePlan = SubscriptionPlan::where('slug', 'free')->first();

        if (!$freePlan) {
            $this->command->error('❌ No se encontró el plan "Free". Ejecuta SubscriptionPlanSeeder primero.');
            return;
        }

        // ================================================================
        // 2. DEFINIR LAS EMPRESAS
        // ================================================================
        $companies = [
            [
                'name' => 'HOSPITAL DE CLINICAS DE CECIAMB, C.A.',
                'business_name' => 'HOSPITAL DE CLINICAS DE CECIAMB, C.A.',
                'tax_id' => 'J-30553771-2',
                'email' => '',
                'phone' => '',
                'address' => '',
                'logo_path' => 'hcc.png',
                'timezone' => 'America/Caracas',
                'subscription_plan_id' => $freePlan->id,
                'subscription_expires_at' => null,
                'is_active' => true,
            ],
            [
                'name' => 'CENTRO DE CIRUGIA AMBULATORIA DR. HECTOR RAFAEL HURTADO',
                'business_name' => 'CENTRO DE CIRUGIA AMBULATORIA',
                'tax_id' => 'J-09517722-0',
                'email' => '',
                'phone' => '',
                'address' => '',
                'logo_path' => 'cca.png',
                'timezone' => 'America/Caracas',
                'subscription_plan_id' => $freePlan->id,
                'subscription_expires_at' => null,
                'is_active' => true,
            ],
            [
                'name' => 'INSTITUTO CARDIOVASCULAR DE GUAYANA, C.A.',
                'business_name' => 'INSTITUTO CARDIOVASCULAR DE GUAYANA, C.Α.',
                'tax_id' => 'J-31225887-0',
                'email' => '',
                'phone' => '',
                'address' => '',
                'logo_path' => 'icg.png',
                'timezone' => 'America/Caracas',
                'subscription_plan_id' => $freePlan->id,
                'subscription_expires_at' => null,
                'is_active' => true,
            ],
            [
                'name' => 'PRECARDIO GUAYANA, C.A.',
                'business_name' => 'PRECARDIO GUAYANA, C.A.',
                'tax_id' => 'J-31664580-0',
                'email' => ' ',
                'phone' => ' ',
                'address' => ' ',
                'logo_path' => 'precardio.png',
                'timezone' => 'America/Caracas',
                'subscription_plan_id' => $freePlan->id,
                'subscription_expires_at' => null,
                'is_active' => true,
            ],
            [
                'name' => 'INSTITUTO CLINICO INFANTIL, C.A',
                'business_name' => 'INSTITUTO CLINICO INFANTIL, C.A',
                'tax_id' => 'J-09509094-9',
                'email' => ' ',
                'phone' => ' ',
                'address' => ' ',
                'logo_path' => 'ici.png',
                'timezone' => 'America/Caracas',
                'subscription_plan_id' => $freePlan->id,
                'subscription_expires_at' => null,
                'is_active' => true,
            ],
            [
                'name' => 'FARMACIA ENTRE RIOS, C.A',
                'business_name' => 'FARMACIA ENTRE RIOS, C.A',
                'tax_id' => 'J-31524446-2',
                'email' => '',
                'phone' => '',
                'address' => '',
                'logo_path' => 'rios.png',
                'timezone' => 'America/Caracas',
                'subscription_plan_id' => $freePlan->id,
                'subscription_expires_at' => null,
                'is_active' => true,
            ],
        ];

        // ================================================================
        // 3. INSERTAR O ACTUALIZAR (EVITAR DUPLICADOS)
        // ================================================================
        foreach ($companies as $company) {
            // Buscar por tax_id (RIF) y actualizar o crear
            Company::updateOrCreate(
                ['tax_id' => $company['tax_id']],
                $company
            );
        }

        // ================================================================
        // 4. VERIFICACIÓN
        // ================================================================
        $this->command->info('✅ Empresas creadas/actualizadas:');
        $this->command->table(
            ['ID', 'Nombre', 'RIF', 'Plan', 'Activo'],
            Company::with('subscriptionPlan')
                ->get()
                ->map(fn($company) => [
                    $company->id,
                    substr($company->name, 0, 30) . '...',
                    $company->tax_id,
                    $company->subscriptionPlan?->name ?? 'N/A',
                    $company->is_active ? '✅ Sí' : '❌ No',
                ])
                ->toArray()
        );
    }
}