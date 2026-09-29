<x-filament-panels::page>
    {{-- Filtros --}}
    <div class="mb-4">
        <x-filament::section>
            <form wire:submit.prevent="refresh">
                {{-- ✅ Formulario en su propio grid --}}
                <div class="mb-4">
                    {{ $this->form }}
                </div>
                
                {{-- ✅ Botón separado --}}
                <div class="flex justify-end">
                    <x-filament::button type="submit" color="primary">
                        Actualizar
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>

    {{-- Resumen con ambas monedas --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        {{-- Total Ingresos --}}
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Total Ingresos</div>
            <div class="text-lg font-bold text-green-600">
                $ {{ number_format($totals['income_original'] ?? 0, 2) }}
            </div>
            <div class="text-xs text-gray-400">
                Bs.S {{ number_format($totals['income_converted'] ?? 0, 2) }}
            </div>
        </x-filament::section>

        {{-- Total Egresos --}}
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Total Egresos</div>
            <div class="text-lg font-bold text-red-600">
                $ {{ number_format($totals['expense_original'] ?? 0, 2) }}
            </div>
            <div class="text-xs text-gray-400">
                Bs.S {{ number_format($totals['expense_converted'] ?? 0, 2) }}
            </div>
        </x-filament::section>

        {{-- Flujo Neto --}}
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Flujo Neto</div>
            <div class="text-lg font-bold {{ ($totals['net_original'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                $ {{ number_format($totals['net_original'] ?? 0, 2) }}
            </div>
            <div class="text-xs text-gray-400">
                Bs.S {{ number_format($totals['net_converted'] ?? 0, 2) }}
            </div>
        </x-filament::section>

        {{-- Transacciones --}}
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Transacciones</div>
            <div class="text-lg font-bold text-blue-600">
                {{ number_format($totals['transaction_count'] ?? 0) }}
            </div>
            <div class="text-xs text-gray-400">
                &nbsp;
            </div>
        </x-filament::section>
    </div>

    {{-- Botones de exportación --}}
    <div class="flex gap-2 mb-4">
        <x-filament::button color="success" wire:click="exportExcel">📊 Exportar Excel</x-filament::button>
        <x-filament::button color="danger" wire:click="exportPdf">📄 Exportar PDF</x-filament::button>
    </div>

    {{-- Resumen por mes --}}
    @foreach ($monthly_summary as $month)
        <x-filament::section class="mb-4">
            {{-- Cabecera del mes --}}
            <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                <h3 class="text-lg font-bold text-primary-600">📅 {{ $month['month_name'] }}</h3>
                <div class="flex flex-wrap gap-4 text-sm">
                    <span class="text-green-600 font-bold">Ingresos: Bs.S
                        {{ number_format($month['totals']['income'], 2) }}</span>
                    <span class="text-red-600 font-bold">Egresos: Bs.S
                        {{ number_format($month['totals']['expense'], 2) }}</span>
                    <span class="font-bold {{ $month['totals']['net'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        Balance: Bs.S {{ number_format($month['totals']['net'], 2) }}
                    </span>
                </div>
            </div>

            {{-- Tabla de INGRESOS --}}
            <div class="mb-3">
                <h4 class="font-bold text-green-600 text-sm mb-1">▼ INGRESOS</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="text-left px-3 py-2 border border-gray-200 dark:border-gray-700">Categoría / Cuenta</th>
                                <th class="text-right px-3 py-2 border border-gray-200 dark:border-gray-700">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($month['income'] ?? [] as $category)
                                <tr class="bg-green-50 dark:bg-green-900/10">
                                    <td class="px-3 py-1.5 border border-gray-200 dark:border-gray-700 font-medium">
                                        {{ $category['category'] }}
                                    </td>
                                    <td class="text-right px-3 py-1.5 border border-gray-200 dark:border-gray-700 font-medium text-green-600">
                                        Bs.S {{ number_format($category['total'], 2) }}
                                    </td>
                                </tr>
                                @foreach ($category['accounts'] ?? [] as $account)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-1 border border-gray-200 dark:border-gray-700 pl-6 text-gray-600 dark:text-gray-400">
                                            {{ $account['account'] }}
                                        </td>
                                        <td class="text-right px-3 py-1 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400">
                                            Bs.S {{ number_format($account['total'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="2" class="px-3 py-2 text-center text-gray-400 border border-gray-200 dark:border-gray-700">
                                        Sin ingresos registrados
                                    </td>
                                </tr>
                            @endforelse
                            <tr class="bg-green-100 dark:bg-green-900/20 font-bold">
                                <td class="px-3 py-1.5 border border-gray-200 dark:border-gray-700 text-green-700 dark:text-green-400">
                                    Total Ingresos {{ $month['month_name'] }}
                                </td>
                                <td class="text-right px-3 py-1.5 border border-gray-200 dark:border-gray-700 text-green-700 dark:text-green-400">
                                    Bs.S {{ number_format($month['totals']['income'], 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tabla de EGRESOS --}}
            <div>
                <h4 class="font-bold text-red-600 text-sm mb-1">▼ EGRESOS</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="text-left px-3 py-2 border border-gray-200 dark:border-gray-700">Categoría / Cuenta</th>
                                <th class="text-right px-3 py-2 border border-gray-200 dark:border-gray-700">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($month['expense'] ?? [] as $category)
                                <tr class="bg-red-50 dark:bg-red-900/10">
                                    <td class="px-3 py-1.5 border border-gray-200 dark:border-gray-700 font-medium">
                                        {{ $category['category'] }}
                                    </td>
                                    <td class="text-right px-3 py-1.5 border border-gray-200 dark:border-gray-700 font-medium text-red-600">
                                        Bs.S {{ number_format($category['total'], 2) }}
                                    </td>
                                </tr>
                                @foreach ($category['accounts'] ?? [] as $account)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-1 border border-gray-200 dark:border-gray-700 pl-6 text-gray-600 dark:text-gray-400">
                                            {{ $account['account'] }}
                                        </td>
                                        <td class="text-right px-3 py-1 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400">
                                            Bs.S {{ number_format($account['total'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="2" class="px-3 py-2 text-center text-gray-400 border border-gray-200 dark:border-gray-700">
                                        Sin egresos registrados
                                    </td>
                                </tr>
                            @endforelse
                            <tr class="bg-red-100 dark:bg-red-900/20 font-bold">
                                <td class="px-3 py-1.5 border border-gray-200 dark:border-gray-700 text-red-700 dark:text-red-400">
                                    Total Egresos {{ $month['month_name'] }}
                                </td>
                                <td class="text-right px-3 py-1.5 border border-gray-200 dark:border-gray-700 text-red-700 dark:text-red-400">
                                    Bs.S {{ number_format($month['totals']['expense'], 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Balance del mes --}}
            <div class="text-right text-sm font-bold mt-2 {{ $month['totals']['net'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                Balance del mes: Bs.S {{ number_format($month['totals']['net'], 2) }}
            </div>
        </x-filament::section>
    @endforeach

    {{-- Totales generales --}}
    <x-filament::section class="mb-4 bg-gray-50 dark:bg-gray-800">
        <div class="flex flex-wrap justify-between items-center gap-4">
            <span class="text-lg font-bold">📊 TOTAL GENERAL</span>
            <div class="flex flex-wrap gap-6">
                <span class="text-green-600 font-bold">Ingresos: Bs.S {{ number_format($totals['income'] ?? 0, 2) }}</span>
                <span class="text-red-600 font-bold">Egresos: Bs.S {{ number_format($totals['expense'] ?? 0, 2) }}</span>
                <span class="font-bold {{ ($totals['net'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    Balance: Bs.S {{ number_format($totals['net'] ?? 0, 2) }}
                </span>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>