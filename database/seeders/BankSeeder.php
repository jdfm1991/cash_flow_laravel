<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bank;

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banks = [
            [
                'id' => 1,
                'name' => 'BANCO PROVINCIAL',
                'code' => '0182',
                'country_code' => 'VE',
                'website' => '',
                'phone' => '',
                'logo_path' => null,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'name' => 'BANCO MERCANTIL',
                'code' => '0105',
                'country_code' => 'VE',
                'website' => '',
                'phone' => '',
                'logo_path' => null,
                'is_active' => true,
            ],
            [
                'id' => 3,
                'name' => 'BANCO DE VENEZUELA',
                'code' => '0102',
                'country_code' => 'VE',
                'website' => '',
                'phone' => '',
                'logo_path' => null,
                'is_active' => true,
            ],
            [
                'id' => 4,
                'name' => 'BANESCO',
                'code' => '0134',
                'country_code' => 'VE',
                'website' => '',
                'phone' => '',
                'logo_path' => null,
                'is_active' => true,
            ],
            [
                'id' => 5,
                'name' => 'BANPLUS',
                'code' => '0174',
                'country_code' => 'VE',
                'website' => '',
                'phone' => '',
                'logo_path' => null,
                'is_active' => true,
            ],
            [
                'id' => 6,
                'name' => 'BANCO CARONI',
                'code' => '0128',
                'country_code' => 'VE',
                'website' => '',
                'phone' => '',
                'logo_path' => null,
                'is_active' => true,
            ],
            [
                'id' => 7,
                'name' => 'BANCO EXTERIOR',
                'code' => '0115',
                'country_code' => 'VE',
                'website' => '',
                'phone' => '',
                'logo_path' => null,
                'is_active' => true,
            ],
            [
                'id' => 999,
                'name' => 'Migración Externa',
                'code' => 'MIG',
                'country_code' => 'EX',
                'website' => null,
                'phone' => null,
                'logo_path' => null,
                'is_active' => true,
            ],
        ];

        foreach ($banks as $bank) {
            Bank::updateOrCreate(
                ['id' => $bank['id']],
                $bank
            );
        }

        // ================================================================
        // 3. VERIFICACIÓN: Mostrar información de los bancos creados
        // ================================================================
        $this->command->info('✅ Bancos creados/actualizados:');
        $this->command->table(
            ['ID', 'Nombre', 'Código', 'País', 'Activo'],
            Bank::orderBy('id')
                ->get()
                ->map(fn($bank) => [
                    $bank->id,
                    $bank->name,
                    $bank->code,
                    $bank->country_name,
                    $bank->is_active ? '✅ Sí' : '❌ No',
                ])
                ->toArray()
        );
    }
}