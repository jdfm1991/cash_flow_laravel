<?php

namespace Database\Seeders;

use App\Models\ExternalConnection;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class ExternalConnectionSeeder extends Seeder
{
    /**
     * Datos de conexiones del sistema actual
     * Basado en el dump de la tabla external_connections
     */
    public function run(): void
    {
        // 1. Verificar que existen empresas
        $companies = Company::all();
        if ($companies->isEmpty()) {
            $this->command->warn('⚠️ No hay empresas en la base de datos. Ejecuta CompanySeeder primero.');
            return;
        }

        // 2. Datos de conexiones (basados en el sistema actual)
        $connections = [
            [
                'company_name' => 'HOSPITAL DE CLINICAS DE CECIAMB, C.A.',
                'name' => 'Sparrow Administrativo (HOSPITAL DE CLINICAS DE CECIAMB, C.A.)',
                'type' => 'migration',
                'host' => '192.168.1.3',
                'port' => 3306,
                'db_name' => 'sparrow_siadcli',
                'username' => 'root',
                'password' => Crypt::encryptString('Www.sisparrow.com/210973'),
                'table_name' => 'adm_bancos_operaciones',
                'field_mapping' => [
                    'date' => 'fecha_operacion',
                    'income' => 'ingresos',
                    'expense' => 'egresos',
                    'reference' => 'numero_documento',
                    'description' => 'conceptos',
                ],
                'query_template' => null,
                'last_sync_at' => null,
                'is_active' => true,
            ],
            [
                'company_name' => 'CENTRO DE CIRUGIA AMBULATORIA',
                'name' => 'Sparrow Administrativo (CENTRO DE CIRUGIA AMBULATORIA)',
                'type' => 'migration',
                'host' => '192.168.1.3',
                'port' => 3308,
                'db_name' => 'sparrow_siadcli',
                'username' => 'root',
                'password' => Crypt::encryptString('Www.sisparrow.com/210973'),
                'table_name' => 'adm_bancos_operaciones',
                'field_mapping' => [
                    'date' => 'fecha_operacion',
                    'income' => 'ingresos',
                    'expense' => 'egresos',
                    'reference' => 'numero_documento',
                    'description' => 'conceptos',
                ],
                'query_template' => null,
                'last_sync_at' => null,
                'is_active' => true,
            ],
            [
                'company_name' => 'INSTITUTO CARDIOVASCULAR DE GUAYANA, C.A.',
                'name' => 'Sparrow Administrativo (INSTITUTO CARDIOVASCULAR DE GUAYANA, C.A.)',
                'type' => 'migration',
                'host' => '192.168.1.3',
                'port' => 3307,
                'db_name' => 'sparrow_siadcli',
                'username' => 'root',
                'password' => Crypt::encryptString('Www.sisparrow.com/210973'),
                'table_name' => 'adm_bancos_operaciones',
                'field_mapping' => null,
                'query_template' => null,
                'last_sync_at' => null,
                'is_active' => true,
            ],
            [
                'company_name' => 'PRECARDIO GUAYANA, C.A.',
                'name' => 'Sparrow Administrativo (PRECARDIO GUAYANA, C.A.)',
                'type' => 'migration',
                'host' => '192.168.1.3',
                'port' => 3309,
                'db_name' => 'sparrow_siadcli',
                'username' => 'root',
                'password' => Crypt::encryptString('Www.sisparrow.com/210973'),
                'table_name' => 'adm_bancos_operaciones',
                'field_mapping' => null,
                'query_template' => null,
                'last_sync_at' => null,
                'is_active' => true,
            ],
            [
                'company_name' => 'INSTITUTO CLINICO INFANTIL, C.A',
                'name' => 'Sparrow Administrativo (INSTITUTO CLINICO INFANTIL, C.A)',
                'type' => 'migration',
                'host' => '192.168.0.3',
                'port' => 3306,
                'db_name' => 'sparrow_siadcli',
                'username' => 'root',
                'password' => Crypt::encryptString('Www.sisparrow.com/210973'),
                'table_name' => 'adm_bancos_operaciones',
                'field_mapping' => null,
                'query_template' => null,
                'last_sync_at' => null,
                'is_active' => true,
            ],
            [
                'company_name' => 'FARMACIA ENTRE RIOS, C.A',
                'name' => 'Sparrow Administrativo (FARMACIA ENTRE RIOS, C.A)',
                'type' => 'migration',
                'host' => '192.168.1.3',
                'port' => 3306,
                'db_name' => 'sparrow_adm',
                'username' => 'root',
                'password' => Crypt::encryptString('Www.sisparrow.com/210973'),
                'table_name' => 'adm_bancos_operaciones',
                'field_mapping' => null,
                'query_template' => null,
                'last_sync_at' => null,
                'is_active' => true,
            ],
        ];

        // 3. Insertar conexiones
        $count = 0;
        foreach ($connections as $data) {
            // Buscar la empresa por nombre
            $company = Company::where('name', 'LIKE', '%' . $data['company_name'] . '%')->first();

            if (!$company) {
                // Si no se encuentra exactamente, buscar por coincidencia parcial
                $company = Company::where('name', 'LIKE', '%' . substr($data['company_name'], 0, 20) . '%')->first();
            }

            if (!$company) {
                $this->command->warn("⚠️ Empresa no encontrada: {$data['company_name']}");
                continue;
            }

            // Encriptar la contraseña
            $encryptedPassword = Crypt::encryptString($data['password']);

            // Crear la conexión
            ExternalConnection::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'type' => $data['type'],
                'host' => $data['host'],
                'port' => $data['port'],
                'db_name' => $data['db_name'],
                'username' => $data['username'],
                'password' => $encryptedPassword,
                'table_name' => $data['table_name'],
                'field_mapping' => $data['field_mapping'],
                'query_template' => $data['query_template'],
                'last_sync_at' => $data['last_sync_at'],
                'is_active' => $data['is_active'],
            ]);

            $count++;
            $this->command->info("✅ Conexión creada para: {$company->name}");
        }

        $this->command->info("✅ Se crearon {$count} conexiones externas.");
    }
}