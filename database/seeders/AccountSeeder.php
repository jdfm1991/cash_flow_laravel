<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔄 Importando cuentas contables desde datos existentes...');

        // ================================================================
        // 1. MAPEO DE CATEGORÍAS
        // ================================================================

        $this->command->info('📂 Mapeando categorías...');

        // Datos extraídos de accounts_old.sql
        // Mapeo: nombre_categoria_en_old -> id_categoria_en_new
        $categoryMapping = [];

        // Buscar categorías en la nueva estructura
        $categories = Category::all();

        // Mapeo por nombre (usando el campo 'category' del SQL old)
        $categoryMap = [
            // Ingresos
            'Alquileres' => 'Alquileres',
            'Servicios' => 'Servicios',
            'Intereses' => 'Intereses',
            'Otros Ingresos' => 'Otros Ingresos',
            'Ventas' => 'Ventas',

            // Egresos
            'Contribuciones' => 'Contribuciones',
            'Nómina' => 'Nómina',
            'Honorarios Médicos' => 'Honorarios Médicos',
            'Suministros' => 'Suministros',
            'Transporte' => 'Transporte',
            'Mantenimiento' => 'Mantenimiento',
            'Otros Egresos' => 'Otros Egresos',
        ];

        foreach ($categoryMap as $oldName => $newName) {
            $category = Category::where('name', $newName)->first();
            if ($category) {
                $categoryMapping[$oldName] = $category->id;
                $this->command->line("  ✅ {$oldName} → ID: {$category->id}");
            } else {
                $this->command->warn("  ⚠️ Categoría no encontrada: {$newName}");
            }
        }

        // ================================================================
        // 2. DATOS DEL SISTEMA ACTUAL (desde accounts_old.sql)
        // ================================================================

        $this->command->info('📥 Importando cuentas...');

        $accountsData = [
            // ============================================================
            // INGRESOS (Income)
            // ============================================================

            // Alquileres
            ['name' => 'Alquiler Local', 'category' => 'Alquileres', 'description' => '', 'is_system' => 1, 'sort_order' => 3],

            // Servicios
            ['name' => 'Consultoría', 'category' => 'Servicios', 'description' => '', 'is_system' => 1, 'sort_order' => 4],

            // Transferencias entre cuentas
            ['name' => 'Transferencias entre cuentas', 'category' => 'Otros Ingresos', 'description' => '', 'is_system' => 0, 'sort_order' => 0],

            // Ventas
            ['name' => 'Ingreso particular', 'category' => 'Ventas', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Ingresos por seguros', 'category' => 'Ventas', 'description' => '', 'is_system' => 0, 'sort_order' => 0],

            // Intereses
            ['name' => 'Comisiones bancarias', 'category' => 'Intereses', 'description' => '', 'is_system' => 0, 'sort_order' => 0],

            // Otros Ingresos
            ['name' => 'Devoluciones', 'category' => 'Otros Ingresos', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Cuentas por Cobrar', 'category' => 'Otros Ingresos', 'description' => '', 'is_system' => 0, 'sort_order' => 0],

            // ============================================================
            // EGRESOS (Expense)
            // ============================================================

            // Otros Egresos
            ['name' => 'Intereses Bancarios', 'category' => 'Otros Egresos', 'description' => '', 'is_system' => 1, 'sort_order' => 5],
            ['name' => 'Transferencias entre cuentas', 'category' => 'Otros Egresos', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Prestamos Bancarios', 'category' => 'Otros Egresos', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Donaciones', 'category' => 'Otros Egresos', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Reintegros', 'category' => 'Otros Egresos', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Fiestas y Celebraciones', 'category' => 'Otros Egresos', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Cuentas por Cobrar', 'category' => 'Otros Egresos', 'description' => '', 'is_system' => 0, 'sort_order' => 0],

            // Contribuciones
            ['name' => 'SENIAT', 'category' => 'Contribuciones', 'description' => 'ISLR - IVA', 'is_system' => 1, 'sort_order' => 1],
            ['name' => 'Alcaldía', 'category' => 'Contribuciones', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'IVSS', 'category' => 'Contribuciones', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'BANAVIH', 'category' => 'Contribuciones', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'INCES', 'category' => 'Contribuciones', 'description' => '', 'is_system' => 0, 'sort_order' => 0],

            // Nómina
            ['name' => 'Nómina Mensual', 'category' => 'Nómina', 'description' => '', 'is_system' => 1, 'sort_order' => 3],
            ['name' => 'Cesta Ticket', 'category' => 'Nómina', 'description' => 'Bono de alimentación', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Prestaciones Sociales', 'category' => 'Nómina', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Utilidades', 'category' => 'Nómina', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Vacaciones', 'category' => 'Nómina', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Bono de Valoración', 'category' => 'Nómina', 'description' => 'Bono de valoración', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Liquidaciones', 'category' => 'Nómina', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Guardias especiales', 'category' => 'Nómina', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Bono de Coordinación', 'category' => 'Nómina', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Viáticos', 'category' => 'Nómina', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Vigilancia', 'category' => 'Nómina', 'description' => '', 'is_system' => 0, 'sort_order' => 0],

            // Honorarios Médicos
            ['name' => 'Honorarios Particulares', 'category' => 'Honorarios Médicos', 'description' => '', 'is_system' => 1, 'sort_order' => 4],
            ['name' => 'Honorarios por Seguro', 'category' => 'Honorarios Médicos', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Honorarios Médicos Residentes', 'category' => 'Honorarios Médicos', 'description' => '', 'is_system' => 0, 'sort_order' => 0],

            // Suministros
            ['name' => 'Materiales administrativos', 'category' => 'Suministros', 'description' => '', 'is_system' => 1, 'sort_order' => 5],
            ['name' => 'Alimentos', 'category' => 'Suministros', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Proveedores de farmacia', 'category' => 'Suministros', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Proveedores de hospital', 'category' => 'Suministros', 'description' => '', 'is_system' => 0, 'sort_order' => 0],

            // Servicios (Expense)
            ['name' => 'Corpoelec', 'category' => 'Servicios', 'description' => 'Energía Eléctrica', 'is_system' => 1, 'sort_order' => 6],
            ['name' => 'Hidrobolívar', 'category' => 'Servicios', 'description' => 'Agua', 'is_system' => 1, 'sort_order' => 7],
            ['name' => 'Digitel', 'category' => 'Servicios', 'description' => 'Internet', 'is_system' => 1, 'sort_order' => 8],
            ['name' => 'Movistar', 'category' => 'Servicios', 'description' => 'Internet', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Inter', 'category' => 'Servicios', 'description' => 'Internet', 'is_system' => 0, 'sort_order' => 0],

            // Transporte
            ['name' => 'Mensajería SR Amilcar', 'category' => 'Transporte', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Taxi Sra Yulmi', 'category' => 'Transporte', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Taxi Yurima', 'category' => 'Transporte', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Transporte del personal', 'category' => 'Transporte', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Taxis Varios', 'category' => 'Transporte', 'description' => '', 'is_system' => 0, 'sort_order' => 0],

            // Mantenimiento
            ['name' => 'Reparaciones y Repuestos', 'category' => 'Mantenimiento', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
            ['name' => 'Mantenimiento general', 'category' => 'Mantenimiento', 'description' => '', 'is_system' => 0, 'sort_order' => 0],
        ];

        // ================================================================
        // 3. INSERTAR O ACTUALIZAR
        // ================================================================

        $created = 0;
        $updated = 0;
        $errors = 0;

        foreach ($accountsData as $accountData) {
            try {
                // Obtener el ID de la categoría
                $categoryId = $categoryMapping[$accountData['category']] ?? null;

                if (!$categoryId) {
                    $this->command->warn("⚠️ Categoría no encontrada: {$accountData['category']} para cuenta {$accountData['name']}");
                    $errors++;
                    continue;
                }

                // Buscar si la cuenta ya existe (por nombre y categoría)
                $existing = Account::where('name', $accountData['name'])
                    ->where('category_id', $categoryId)
                    ->first();

                if ($existing) {
                    // Actualizar
                    $existing->update([
                        'description' => $accountData['description'],
                        'is_system' => (bool) $accountData['is_system'],
                        'sort_order' => $accountData['sort_order'],
                        'is_active' => true,
                    ]);
                    $updated++;
                    $this->command->line("  🔄 Actualizada: {$accountData['name']}");
                } else {
                    // Crear
                    Account::create([
                        'name' => $accountData['name'],
                        'category_id' => $categoryId,
                        'description' => $accountData['description'],
                        'is_system' => (bool) $accountData['is_system'],
                        'sort_order' => $accountData['sort_order'],
                        'is_active' => true,
                    ]);
                    $created++;
                    $this->command->line("  ✅ Creada: {$accountData['name']}");
                }
            } catch (\Exception $e) {
                $this->command->error("❌ Error al procesar cuenta: {$accountData['name']} - {$e->getMessage()}");
                $errors++;
            }
        }

        // ================================================================
        // 4. VERIFICACIÓN FINAL
        // ================================================================

        $this->command->newLine();
        $this->command->info('✅ IMPORTACIÓN COMPLETADA');
        $this->command->newLine();

        $this->command->info('📊 Resumen:');
        $this->command->table(
            ['Acción', 'Cantidad'],
            [
                ['Creadas', $created],
                ['Actualizadas', $updated],
                ['Errores', $errors],
                ['Total', count($accountsData)],
            ]
        );

        $this->command->newLine();
        $this->command->info('📋 Listado completo de cuentas por categoría:');
        $this->command->table(
            ['ID', 'Cuenta', 'Categoría', 'Sistema', 'Orden'],
            Account::with('category')
                ->orderBy('category_id')
                ->orderBy('sort_order')
                ->get()
                ->map(fn($account) => [
                    $account->id,
                    substr($account->name, 0, 25) . (strlen($account->name) > 25 ? '...' : ''),
                    $account->category?->name ?? 'N/A',
                    $account->is_system ? '✅' : '❌',
                    $account->sort_order,
                ])
                ->toArray()
        );

        $this->command->newLine();
        $this->command->info('🎯 ¡Cuentas importadas exitosamente!');
    }
}