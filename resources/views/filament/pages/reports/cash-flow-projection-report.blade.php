<x-filament-panels::page>
    {{-- Filtros --}}
    <div class="mb-4">
        <x-filament::section>
            <form wire:submit.prevent="refresh" class="mb-4">
                {{ $this->form }}
                <div class="flex items-end gap-2">
                    <x-filament::button type="submit" color="primary">
                        Calcular
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>

    {{-- Resumen ejecutivo --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Saldo Inicial</div>
            <div class="text-lg font-bold text-blue-600">
                Bs.S {{ number_format($summary['starting_balance'] ?? 0, 2) }}
            </div>
        </x-filament::section>
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Saldo Final</div>
            <div
                class="text-lg font-bold {{ ($summary['final_balance'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                Bs.S {{ number_format($summary['final_balance'] ?? 0, 2) }}
            </div>
        </x-filament::section>
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Días de Cobertura</div>
            <div
                class="text-lg font-bold {{ ($metrics['days_of_coverage'] ?? 0) >= 30 ? 'text-green-600' : 'text-yellow-600' }}">
                {{ $metrics['days_of_coverage'] ?? 0 }} días
            </div>
        </x-filament::section>
        <x-filament::section class="text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Tendencia</div>
            <div class="text-lg font-bold {{ $metrics['flow_trend_color'] ?? '' }}">
                {{ $metrics['flow_trend'] ?? 0 }}%
                {{ $metrics['flow_trend_label'] ?? '' }}
            </div>
        </x-filament::section>
    </div>

    {{-- Alertas --}}
    @if (!empty($alerts))
        <div class="mb-4">
            @foreach ($alerts as $alert)
                <x-filament::section
                    class="mb-2 {{ $alert['type'] === 'danger' ? 'bg-red-50 dark:bg-red-900/20' : 'bg-yellow-50 dark:bg-yellow-900/20' }}">
                    <div
                        class="flex items-center gap-2 {{ $alert['type'] === 'danger' ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400' }}">
                        <span class="text-lg">{{ $alert['type'] === 'danger' ? '🔴' : '🟡' }}</span>
                        <span>{{ $alert['message'] }}</span>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

    {{-- Gráfico --}}
    <x-filament::section class="mb-4">
        <div style="height: 350px;">
            <canvas id="projectionChart" wire:ignore></canvas>
        </div>
        <div class="text-sm text-center text-gray-500 dark:text-gray-400 mt-2">
            Escenario: {{ $scenario_label ?? 'Realista' }}
            <span class="inline-block w-3 h-3 rounded-full ml-2"
                style="background-color: {{ $scenario_color ?? '#4f46e5' }};"></span>
        </div>
    </x-filament::section>

    {{-- Tabla de proyección detallada --}}
    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="text-left px-3 py-2 border border-gray-200 dark:border-gray-700">Mes</th>
                        <th class="text-right px-3 py-2 border border-gray-200 dark:border-gray-700">Ingresos</th>
                        <th class="text-right px-3 py-2 border border-gray-200 dark:border-gray-700">Egresos</th>
                        <th class="text-right px-3 py-2 border border-gray-200 dark:border-gray-700">Flujo Neto</th>
                        <th class="text-right px-3 py-2 border border-gray-200 dark:border-gray-700">Saldo Proyectado
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($projection as $month)
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-3 py-2 font-medium">{{ $month['month_name'] }}</td>
                            <td class="text-right px-3 py-2 text-green-600">
                                Bs.S {{ number_format($month['income'], 2) }}
                            </td>
                            <td class="text-right px-3 py-2 text-red-600">
                                Bs.S {{ number_format($month['expense'], 2) }}
                            </td>
                            <td
                                class="text-right px-3 py-2 {{ $month['net'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                Bs.S {{ number_format($month['net'], 2) }}
                            </td>
                            <td
                                class="text-right px-3 py-2 font-bold {{ $month['balance'] >= 0 ? 'text-blue-600' : 'text-red-600' }}">
                                Bs.S {{ number_format($month['balance'], 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Botones de exportación --}}
    <div class="flex gap-2 mt-4">
        <x-filament::button color="danger" wire:click="exportPdf">
            📄 Exportar PDF
        </x-filament::button>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            let chartInstance = null;

            function initChart() {
                const canvas = document.getElementById('projectionChart');
                if (!canvas) return;

                if (chartInstance) {
                    chartInstance.destroy();
                    chartInstance = null;
                }

                const ctx = canvas.getContext('2d');

                const labels = @json($labels ?? []);
                const incomeData = @json($incomeData ?? []);
                const expenseData = @json($expenseData ?? []);
                const balanceData = @json($balanceData ?? []);

                if (labels.length === 0) {
                    canvas.parentElement.innerHTML =
                        '<div class="flex items-center justify-center h-full text-gray-500">No hay datos para proyectar</div>';
                    return;
                }

                chartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                                label: 'Ingresos Proyectados',
                                data: incomeData,
                                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                                borderColor: 'rgb(16, 185, 129)',
                                borderWidth: 1,
                                borderRadius: 4,
                            },
                            {
                                label: 'Egresos Proyectados',
                                data: expenseData,
                                backgroundColor: 'rgba(239, 68, 68, 0.7)',
                                borderColor: 'rgb(239, 68, 68)',
                                borderWidth: 1,
                                borderRadius: 4,
                            },
                            {
                                label: 'Saldo Proyectado',
                                data: balanceData,
                                type: 'line',
                                borderColor: 'rgb(79, 70, 229)',
                                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.3,
                                pointBackgroundColor: 'rgb(79, 70, 229)',
                                pointRadius: 5,
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
                                text: 'Proyección de Flujo de Caja - {{ $scenario_label ?? 'Realista' }}',
                                font: {
                                    size: 14,
                                    weight: 'bold',
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': Bs.S ' + context.parsed.y
                                    .toLocaleString();
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
                setTimeout(initChart, 200);
            });
            document.addEventListener('livewire:navigated', function() {
                setTimeout(initChart, 200);
            });
        </script>
    @endpush
</x-filament-panels::page>
