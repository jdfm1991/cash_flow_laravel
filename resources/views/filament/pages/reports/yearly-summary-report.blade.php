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
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Total Ingresos</div>
            <div class="text-lg font-bold text-green-600">
                VES {{ number_format($totals['income_original'] ?? 0, 2) }}
            </div>
            <div class="text-xs text-gray-400">
                USD {{ number_format($totals['income'] ?? 0, 2) }}
            </div>
        </x-filament::section>
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Total Egresos</div>
            <div class="text-lg font-bold text-red-600">
                VES {{ number_format($totals['expense_original'] ?? 0, 2) }}
            </div>
            <div class="text-xs text-gray-400">
                USD {{ number_format($totals['expense'] ?? 0, 2) }}
            </div>
        </x-filament::section>
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Balance Neto</div>
            <div class="text-lg font-bold {{ ($totals['net_original'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                VES {{ number_format($totals['net_original'] ?? 0, 2) }}
            </div>
            <div class="text-xs text-gray-400">
                USD {{ number_format($totals['net'] ?? 0, 2) }}
            </div>
        </x-filament::section>
    </div>

    {{-- Gráfico --}}
    <x-filament::section class="mb-4">
        <div style="height: 350px;">
            <canvas id="yearlyChart" wire:ignore></canvas>
        </div>
    </x-filament::section>

    {{-- Tabla --}}
    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="text-left px-3 py-2 border border-gray-200 dark:border-gray-700">Año</th>
                        <th class="text-right px-3 py-2 border border-gray-200 dark:border-gray-700">Ingresos (VES)</th>
                        <th class="text-right px-3 py-2 border border-gray-200 dark:border-gray-700">Ingresos (USD)</th>
                        <th class="text-right px-3 py-2 border border-gray-200 dark:border-gray-700">Egresos (VES)</th>
                        <th class="text-right px-3 py-2 border border-gray-200 dark:border-gray-700">Egresos (USD)</th>
                        <th class="text-right px-3 py-2 border border-gray-200 dark:border-gray-700">Balance (USD)</th>
                        <th class="text-right px-3 py-2 border border-gray-200 dark:border-gray-700">N° Ingresos</th>
                        <th class="text-right px-3 py-2 border border-gray-200 dark:border-gray-700">N° Egresos</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalIncomeOriginal = 0;
                        $totalIncome = 0;
                        $totalExpenseOriginal = 0;
                        $totalExpense = 0;
                        $totalNet = 0;
                    @endphp
                    @foreach($years as $year)
                        @php
                            $totalIncomeOriginal += $year['income_original'] ?? 0;
                            $totalIncome += $year['income'] ?? 0;
                            $totalExpenseOriginal += $year['expense_original'] ?? 0;
                            $totalExpense += $year['expense'] ?? 0;
                            $totalNet += $year['net'] ?? 0;
                        @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-3 py-2 font-medium">{{ $year['year'] }}</td>
                            <td class="text-right px-3 py-2 text-green-600">
                                VES {{ number_format($year['income_original'] ?? 0, 2) }}
                            </td>
                            <td class="text-right px-3 py-2 text-green-600">
                                USD {{ number_format($year['income'] ?? 0, 2) }}
                            </td>
                            <td class="text-right px-3 py-2 text-red-600">
                                VES {{ number_format($year['expense_original'] ?? 0, 2) }}
                            </td>
                            <td class="text-right px-3 py-2 text-red-600">
                                USD {{ number_format($year['expense'] ?? 0, 2) }}
                            </td>
                            <td class="text-right px-3 py-2 font-bold {{ $year['net'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                USD {{ number_format($year['net'] ?? 0, 2) }}
                            </td>
                            <td class="text-right px-3 py-2">{{ $year['income_count'] ?? 0 }}</td>
                            <td class="text-right px-3 py-2">{{ $year['expense_count'] ?? 0 }}</td>
                        </tr>
                    @endforeach
                    <tr class="bg-gray-100 dark:bg-gray-700 font-bold border-t-2 border-gray-300 dark:border-gray-600">
                        <td class="px-3 py-2">TOTAL</td>
                        <td class="text-right px-3 py-2 text-green-600">
                            VES {{ number_format($totalIncomeOriginal, 2) }}
                        </td>
                        <td class="text-right px-3 py-2 text-green-600">
                            USD {{ number_format($totalIncome, 2) }}
                        </td>
                        <td class="text-right px-3 py-2 text-red-600">
                            VES {{ number_format($totalExpenseOriginal, 2) }}
                        </td>
                        <td class="text-right px-3 py-2 text-red-600">
                            USD {{ number_format($totalExpense, 2) }}
                        </td>
                        <td class="text-right px-3 py-2 {{ $totalNet >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            USD {{ number_format($totalNet, 2) }}
                        </td>
                        <td class="text-right px-3 py-2">-</td>
                        <td class="text-right px-3 py-2">-</td>
                    </tr>
                </tbody>
            </table>
        </div>
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

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            let chartInstance = null;

            function initChart() {
                const canvas = document.getElementById('yearlyChart');
                if (!canvas) return;
                
                if (chartInstance) {
                    chartInstance.destroy();
                    chartInstance = null;
                }

                const ctx = canvas.getContext('2d');
                
                const labels = @json($labels ?? []);
                const incomeData = @json($incomeData ?? []);
                const expenseData = @json($expenseData ?? []);
                const netData = @json($netData ?? []);

                if (labels.length === 0) {
                    canvas.parentElement.innerHTML =
                        '<div class="flex items-center justify-center h-full text-gray-500">No hay datos para mostrar</div>';
                    return;
                }

                chartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Ingresos',
                                data: incomeData,
                                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                                borderColor: 'rgb(16, 185, 129)',
                                borderWidth: 1,
                                borderRadius: 4,
                            },
                            {
                                label: 'Egresos',
                                data: expenseData,
                                backgroundColor: 'rgba(239, 68, 68, 0.7)',
                                borderColor: 'rgb(239, 68, 68)',
                                borderWidth: 1,
                                borderRadius: 4,
                            },
                            {
                                label: 'Balance Neto',
                                data: netData,
                                type: 'line',
                                borderColor: 'rgb(79, 70, 229)',
                                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                pointBackgroundColor: 'rgb(79, 70, 229)',
                                pointRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                }
                            },
                            title: {
                                display: true,
                                text: 'Resumen Anual de Ingresos vs Egresos',
                                font: {
                                    size: 14,
                                    weight: 'bold',
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'Bs.S ' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', initChart);
            document.addEventListener('livewire:update', function() {
                setTimeout(initChart, 100);
            });
            document.addEventListener('livewire:navigated', function() {
                setTimeout(initChart, 100);
            });
        </script>
    @endpush
</x-filament-panels::page>