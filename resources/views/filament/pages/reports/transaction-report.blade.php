<x-filament-panels::page>
    {{-- Filtros --}}
    <div class="mb-4">
        <x-filament::section>
            <form wire:submit.prevent="refresh" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{ $this->form }}
                <div class="flex items-end gap-2">
                    <x-filament::button type="submit" color="primary">
                        Actualizar
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>

    {{-- Resumen de totales con ambas monedas --}}
    <div class="grid grid-cols-3 gap-4 mb-4">
        {{-- Total Movimientos --}}
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Total Movimientos</div>
            <div class="text-lg font-bold text-blue-600">
                $ {{ number_format($summary['total_original'] ?? 0, 2) }}
            </div>
            <div class="text-xs text-gray-400">
                Bs.S {{ number_format($summary['total'] ?? 0, 2) }}
            </div>
            <div class="text-xs text-gray-400 mt-1">
                {{ number_format($summary['count'] ?? 0) }} transacciones
            </div>
        </x-filament::section>

        {{-- Total Ingresos --}}
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Total Ingresos</div>
            <div class="text-lg font-bold text-green-600">
                $ {{ number_format($summary['income_original'] ?? 0, 2) }}
            </div>
            <div class="text-xs text-gray-400">
                Bs.S {{ number_format($summary['income'] ?? 0, 2) }}
            </div>
            <div class="text-xs text-gray-400 mt-1">
                {{ number_format($summary['income_count'] ?? 0) }} transacciones
            </div>
        </x-filament::section>

        {{-- Total Egresos --}}
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Total Egresos</div>
            <div class="text-lg font-bold text-red-600">
                $ {{ number_format($summary['expense_original'] ?? 0, 2) }}
            </div>
            <div class="text-xs text-gray-400">
                Bs.S {{ number_format($summary['expense'] ?? 0, 2) }}
            </div>
            <div class="text-xs text-gray-400 mt-1">
                {{ number_format($summary['expense_count'] ?? 0) }} transacciones
            </div>
        </x-filament::section>
    </div>

    {{-- Información de filtros --}}
    @if(!empty($filters))
        <div class="flex flex-wrap gap-2 mb-4 text-xs text-gray-500">
            <span class="font-medium">Filtros aplicados:</span>
            @if($filters['type'] ?? false)
                <span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded">Tipo: {{ $filters['type'] }}</span>
            @endif
            @if($filters['account'] ?? false)
                <span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded">Cuenta: {{ $filters['account'] }}</span>
            @endif
            @if($filters['category'] ?? false)
                <span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded">Categoría: {{ $filters['category'] }}</span>
            @endif
        </div>
    @endif

    {{-- Tabla de transacciones --}}
    <x-filament::section>
        {{ $this->table }}
    </x-filament::section>

    {{-- Botones de exportación --}}
    <div class="flex gap-2 mt-4">
        <x-filament::button color="success" wire:click="exportExcel">
            📊 Exportar Excel
        </x-filament::button>
        <x-filament::button color="danger" wire:click="exportPdf">
            📄 Exportar PDF
        </x-filament::button>
    </div>
</x-filament-panels::page>