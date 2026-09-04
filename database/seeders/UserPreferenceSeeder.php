<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserPreference;

class UserPreferenceSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener todos los usuarios
        $users = User::all();

        foreach ($users as $user) {
            // Crear preferencias por defecto para cada usuario
            UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'language' => 'es',
                    'timezone' => 'America/Caracas',
                    'theme' => 'light',
                    'date_format' => 'Y-m-d',
                    'time_format' => 'H:i',
                    'currency_display' => 'symbol',
                    'notifications_email' => true,
                    'notifications_push' => true,
                    'notifications_in_app' => true,
                    'dashboard_layout' => null,
                    'dashboard_widgets' => null,
                    'items_per_page' => 25,
                    'auto_save_reports' => false,
                    'favorite_reports' => null,
                ]
            );
        }

        $this->command->info('✅ Preferencias de usuario creadas para ' . $users->count() . ' usuarios.');
    }
}