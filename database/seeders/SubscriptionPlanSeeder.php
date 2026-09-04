<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;
use App\Models\Currency;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ================================================================
        // 1. OBTENER LA MONEDA BASE (VES)
        // ================================================================
        $baseCurrency = Currency::where('is_base', true)->first();

        // Si no hay moneda base, buscar VES por código
        if (!$baseCurrency) {
            $baseCurrency = Currency::where('code', 'VES')->first();
        }

        // Si aún no hay moneda, usar la primera disponible
        if (!$baseCurrency) {
            $baseCurrency = Currency::first();
        }

        // ================================================================
        // 2. DEFINIR LOS PLANES
        // ================================================================
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Plan gratuito para empresas pequeñas o en etapa inicial. Incluye funcionalidades básicas para comenzar a gestionar el flujo de caja.',
                'max_users' => 5,
                'max_bank_accounts' => 50,
                'max_transactions_per_month' => 500,
                'features' => ['reports', 'basic_export'],
                'price' => 0.00,
                'currency_id' => null,  // Gratis no necesita moneda
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Plan para empresas en crecimiento que necesitan más capacidad y funcionalidades avanzadas.',
                'max_users' => 20,
                'max_bank_accounts' => 100,
                'max_transactions_per_month' => 2000,
                'features' => ['reports', 'export', 'import', 'categories'],
                'price' => 29.99,
                'currency_id' => $baseCurrency?->id,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Plan completo para empresas establecidas que necesitan todas las funcionalidades y mayor capacidad.',
                'max_users' => 50,
                'max_bank_accounts' => 200,
                'max_transactions_per_month' => 5000,
                'features' => ['reports', 'export', 'import', 'categories', 'api', 'custom_reports'],
                'price' => 49.99,
                'currency_id' => $baseCurrency?->id,
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Plan para grandes corporaciones con necesidades ilimitadas y soporte prioritario.',
                'max_users' => 999,
                'max_bank_accounts' => 999,
                'max_transactions_per_month' => 999999,
                'features' => ['all'],
                'price' => 99.99,
                'currency_id' => $baseCurrency?->id,
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        // ================================================================
        // 3. INSERTAR O ACTUALIZAR PLANES
        // ================================================================
        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        // ================================================================
        // 4. VERIFICACIÓN: Mostrar información de los planes creados
        // ================================================================
        $this->command->info('✅ Planes de suscripción creados/actualizados:');
        $this->command->table(
            ['ID', 'Nombre', 'Slug', 'Precio', 'Moneda'],
            SubscriptionPlan::with('currency')
                ->get()
                ->map(fn($plan) => [
                    $plan->id,
                    $plan->name,
                    $plan->slug,
                    number_format($plan->price, 2),
                    $plan->currency?->code ?? 'N/A',
                ])
                ->toArray()
        );
    }
}