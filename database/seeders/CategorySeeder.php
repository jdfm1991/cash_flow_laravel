<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔄 Importando categorías desde datos existentes...');

        // ================================================================
        // 1. CREAR CATEGORÍAS RAÍZ (Nivel 1)
        // ================================================================

        $this->command->info('📂 Creando categorías raíz...');

        $ingresos = Category::updateOrCreate(
            ['name' => 'Ingresos', 'type' => 'income', 'parent_id' => null],
            [
                'code' => '4',
                'icon' => 'bi-arrow-up-circle',
                'color' => '#198754',
                'is_system' => true,
                'sort_order' => 1,
                'description' => 'Todos los ingresos de la empresa',
                'is_active' => true,
            ]
        );

        $egresos = Category::updateOrCreate(
            ['name' => 'Egresos', 'type' => 'expense', 'parent_id' => null],
            [
                'code' => '5',
                'icon' => 'bi-arrow-down-circle',
                'color' => '#dc3545',
                'is_system' => true,
                'sort_order' => 2,
                'description' => 'Todos los egresos de la empresa',
                'is_active' => true,
            ]
        );

        // ================================================================
        // 2. CREAR CATEGORÍAS DE INGRESOS (Nivel 2) - AGRUPADORAS
        // ================================================================

        $this->command->info('📂 Creando grupos de ingresos...');

        $ingresosOperacionales = Category::updateOrCreate(
            ['name' => 'Ingresos Operacionales', 'type' => 'income', 'parent_id' => $ingresos->id],
            [
                'code' => '4.1',
                'icon' => 'bi-briefcase',
                'color' => '#0d6efd',
                'is_system' => true,
                'sort_order' => 1,
                'description' => 'Ingresos por actividad principal',
                'is_active' => true,
            ]
        );

        $ingresosNoOperacionales = Category::updateOrCreate(
            ['name' => 'Ingresos No Operacionales', 'type' => 'income', 'parent_id' => $ingresos->id],
            [
                'code' => '4.2',
                'icon' => 'bi-gift',
                'color' => '#6f42c1',
                'is_system' => true,
                'sort_order' => 2,
                'description' => 'Ingresos por actividades no principales',
                'is_active' => true,
            ]
        );

        // ================================================================
        // 3. CREAR CATEGORÍAS DE EGRESOS (Nivel 2) - AGRUPADORAS
        // ================================================================

        $this->command->info('📂 Creando grupos de egresos...');

        $gastosOperacionales = Category::updateOrCreate(
            ['name' => 'Gastos Operacionales', 'type' => 'expense', 'parent_id' => $egresos->id],
            [
                'code' => '5.1',
                'icon' => 'bi-building',
                'color' => '#dc3545',
                'is_system' => true,
                'sort_order' => 1,
                'description' => 'Gastos de la actividad principal',
                'is_active' => true,
            ]
        );

        $gastosNoOperacionales = Category::updateOrCreate(
            ['name' => 'Gastos No Operacionales', 'type' => 'expense', 'parent_id' => $egresos->id],
            [
                'code' => '5.2',
                'icon' => 'bi-box-seam',
                'color' => '#6c757d',
                'is_system' => true,
                'sort_order' => 2,
                'description' => 'Gastos de actividades no principales',
                'is_active' => true,
            ]
        );

        // ================================================================
        // 4. DATOS DEL SISTEMA ACTUAL (desde el SQL)
        // ================================================================

        $this->command->info('📥 Importando categorías del sistema actual...');

        // ================================================================
        // 4a. CATEGORÍAS DE INGRESOS (Nivel 3)
        // ================================================================

        $incomeCategories = [
            [
                'name' => 'Ventas',
                'icon' => 'bi-tag',
                'color' => '#095318',
                'sort_order' => 1,
                'description' => null,
            ],
            [
                'name' => 'Cobro de seguros',
                'icon' => 'bi-cart',
                'color' => '#db004d',
                'sort_order' => 2,
                'description' => null,
            ],
            [
                'name' => 'Alquileres',
                'icon' => 'bi-house',
                'color' => '#17a2b8',
                'sort_order' => 3,
                'description' => null,
            ],
            [
                'name' => 'Servicios',
                'icon' => 'bi-gear',
                'color' => '#20c997',
                'sort_order' => 4,
                'description' => null,
            ],
            [
                'name' => 'Intereses',
                'icon' => 'bi-percent',
                'color' => '#fd7e14',
                'sort_order' => 5,
                'description' => null,
            ],
            [
                'name' => 'Otros Ingresos',
                'icon' => 'bi-plus-circle',
                'color' => '#6c757d',
                'sort_order' => 6,
                'description' => null,
            ],
        ];

        foreach ($incomeCategories as $data) {
            // Determinar el padre según el nombre
            $parent = $ingresosOperacionales;
            
            // Si es "Otros Ingresos", va a No Operacionales
            if ($data['name'] === 'Otros Ingresos') {
                $parent = $ingresosNoOperacionales;
            }

            Category::updateOrCreate(
                ['name' => $data['name'], 'type' => 'income', 'parent_id' => $parent->id],
                [
                    'icon' => $data['icon'],
                    'color' => $data['color'],
                    'sort_order' => $data['sort_order'],
                    'description' => $data['description'],
                    'is_system' => false,
                    'is_active' => true,
                ]
            );
        }

        // ================================================================
        // 4b. CATEGORÍAS DE EGRESOS (Nivel 3)
        // ================================================================

        $expenseCategories = [
            [
                'name' => 'Contribuciones',
                'icon' => 'bi-receipt',
                'color' => '#c10b0b',
                'sort_order' => 1,
                'description' => "- SENIAT\n- ALCALDÍA\n- INCES\n- IVSS\n- BANAVIH\n- LOCTI\n- ANTIDROGAS",
            ],
            [
                'name' => 'Nómina',
                'icon' => 'bi-people',
                'color' => '#fd7e14',
                'sort_order' => 2,
                'description' => "- NÓMINA ADMINISTRATIVA Y OPERATIVA\n- CESTA BÁSICA DE ALIMENTACIÓN\n- PRESTACIONES SOCIALES\n- LIQUIDACIONES\n- UTILIDADES\n- VACACIONES\n- BONOS\n- TRANSPORTE DE PERSONAL",
            ],
            [
                'name' => 'Honorarios Médicos',
                'icon' => 'bi-briefcase',
                'color' => '#6f42c1',
                'sort_order' => 3,
                'description' => "- PARTICULARES\n- HONORARIOS POR SEGUROS\n- MÉDICOS RESIDENTES (QUINCENA)",
            ],
            [
                'name' => 'Proveedores',
                'icon' => 'bi-truck',
                'color' => '#20c997',
                'sort_order' => 4,
                'description' => null,
            ],
            [
                'name' => 'Servicios',
                'icon' => 'bi-lightbulb',
                'color' => '#c40e44',
                'sort_order' => 5,
                'description' => "- CORPOELEC\n- DIGITEL INTERNET\n- DIGITEL TELEFÓNO MÓVIL\n- MOVISTAR\n- HIDROBOLÍVAR\n- SUPRAGUAYANA\n- SOFTWARE SPARROW\n- SERVICIOS DE PROVEEDORES\n- GESTIÓN DE DESECHOS BIOINFECCIOSOS",
            ],
            [
                'name' => 'Alquileres',
                'icon' => 'bi-building',
                'color' => '#17a2b8',
                'sort_order' => 6,
                'description' => null,
            ],
            [
                'name' => 'Mantenimiento',
                'icon' => 'bi-tools',
                'color' => '#303030',
                'sort_order' => 7,
                'description' => null,
            ],
            [
                'name' => 'Publicidad',
                'icon' => 'bi-megaphone',
                'color' => '#3e9d0c',
                'sort_order' => 8,
                'description' => null,
            ],
            [
                'name' => 'Transporte',
                'icon' => 'bi-bus-front',
                'color' => '#cc8b00',
                'sort_order' => 9,
                'description' => "- TRANSPORTE DE PERSONAL\n- TAXIS VARIOS",
            ],
            [
                'name' => 'Suministros',
                'icon' => 'bi-tag',
                'color' => '#05427a',
                'sort_order' => 10,
                'description' => "- GASES MEDICINALES (NITROX)\n- SUMINISTRO DE LABORATORIO\n- EQUIPO MÉDICO (ECO)\n- MATERIAL DE TOMÓGRAFO (TELECOMSOFT)\n- MATERIALES ADMINISTRATIVOS\n- INSUMOS OPERATIVOS",
            ],
            [
                'name' => 'Otros Egresos',
                'icon' => 'bi-dash-circle',
                'color' => '#426161',
                'sort_order' => 11,
                'description' => null,
            ],
        ];

        foreach ($expenseCategories as $data) {
            // Determinar el padre según el nombre
            $parent = $gastosOperacionales;
            
            // Si es "Otros Egresos", va a No Operacionales
            if ($data['name'] === 'Otros Egresos') {
                $parent = $gastosNoOperacionales;
            }

            Category::updateOrCreate(
                ['name' => $data['name'], 'type' => 'expense', 'parent_id' => $parent->id],
                [
                    'icon' => $data['icon'],
                    'color' => $data['color'],
                    'sort_order' => $data['sort_order'],
                    'description' => $data['description'],
                    'is_system' => false,
                    'is_active' => true,
                ]
            );
        }

        // ================================================================
        // 5. VERIFICACIÓN FINAL
        // ================================================================

        $this->command->newLine();
        $this->command->info('✅ IMPORTACIÓN COMPLETADA');
        $this->command->newLine();

        $this->command->info('📊 Resumen de categorías:');
        $this->command->table(
            ['Nivel', 'Cantidad'],
            [
                ['Raíz (Nivel 1)', Category::whereNull('parent_id')->count()],
                ['Nivel 2', Category::whereNotNull('parent_id')->whereHas('parent', fn($q) => $q->whereNull('parent_id'))->count()],
                ['Nivel 3', Category::whereNotNull('parent_id')->whereHas('parent', fn($q) => $q->whereNotNull('parent_id'))->count()],
                ['Total', Category::count()],
            ]
        );

        $this->command->newLine();
        $this->command->info('📋 Listado completo de categorías:');
        $this->command->table(
            ['ID', 'Nombre', 'Tipo', 'Padre', 'Código', 'Color'],
            Category::with('parent')
                ->orderBy('type')
                ->orderBy('sort_order')
                ->get()
                ->map(fn($cat) => [
                    $cat->id,
                    $cat->name,
                    $cat->type_label,
                    $cat->parent?->name ?? 'Raíz',
                    $cat->code ?? 'N/A',
                    $cat->color,
                ])
                ->toArray()
        );

        $this->command->newLine();
        $this->command->info('🎯 ¡Categorías importadas exitosamente!');
    }
}