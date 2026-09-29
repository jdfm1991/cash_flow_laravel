<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ================================================================
        // ORDEN CORRECTO (por dependencias)
        // ================================================================

        // 1. Monedas (sin dependencias)
        $this->call(CurrencySeeder::class);

        // 2. Planes de suscripción (dependen de currencies)
        $this->call(SubscriptionPlanSeeder::class);

        // 3. Bancos (sin dependencias)
        $this->call(BankSeeder::class);

        // 4. Empresas (dependen de subscription_plans)
        $this->call(CompanySeeder::class);

        // 5. Usuarios (dependen de companies)
        $this->call(UserSeeder::class);

        // 6. Preferencias de usuario (dependen de users)
        $this->call(UserPreferenceSeeder::class);

        // 7. Permisos (dependen de roles)
        $this->call(PermissionsSeeder::class);

        // 8. Categorias (sin dependencias)
        $this->call(CategorySeeder::class);

        // 9. Cuentas (dependen de categories)
        $this->call(AccountSeeder::class);

        // 10. Tasas de cambio (sin dependencias)
        $this->call(ExchangeRateSeeder::class);

        // 11. Acciones (sin dependencias)
        $this->call(AuditLogSeeder::class);

        // 12. Transacciones (dependen de accounts)
        $this->call(TransactionSeeder::class);

        // 13. Cuentas bancarias (dependen de companies)
        $this->call(BankAccountSeeder::class);

        // 13. Sesiones de importación (dependen de users)
        $this->call(ImportSessionSeeder::class);

        // 14. Conexiones externas (sin dependencias)
        $this->call(ExternalConnectionSeeder::class);

        $this->command->info('✅ Todos los seeders ejecutados correctamente.');
    }
}