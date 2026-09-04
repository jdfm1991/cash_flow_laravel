<?php

namespace App\Filament\Widgets;

use App\Models\Currency;
use App\Models\Transaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class IncomeDistribution extends ChartWidget
{
    protected ?string $heading = 'Distribución de Ingresos por Categoría';
    protected int | string | array $columnSpan = 'half';
    protected static ?int $sort = 2;
    protected ?string $pollingInterval = '10s';

    protected function getData(): array
    {
        $companyId = auth()->user()?->current_company_id;

        if (!$companyId) {
            return $this->getEmptyData();
        }

        // ✅ Últimos 30 días con datos
        $lastTransactionDate = Transaction::where('company_id', $companyId)
            ->orderBy('date', 'desc')
            ->value('date');

        if (!$lastTransactionDate) {
            return $this->getEmptyData();
        }

        $endDate = \Carbon\Carbon::parse($lastTransactionDate)->toDateString();
        $startDate = \Carbon\Carbon::parse($lastTransactionDate)->subDays(29)->toDateString();

        // ✅ Obtener transacciones de ingresos agrupadas por categoría
        $data = Transaction::where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', 'income')
            ->select('category_id', DB::raw('SUM(amount_converted) as total'))
            ->with('category')
            ->groupBy('category_id')
            ->orderBy('total', 'desc')
            ->limit(8)
            ->get();

        $labels = [];
        $values = [];
        $colors = [];

        foreach ($data as $item) {
            $labels[] = $item->category?->name ?? 'Sin categoría';
            $values[] = round($item->total, 2);
            $colors[] = $item->category?->color ?? '#10b981';
        }

        // ✅ Si no hay datos, mostrar mensaje
        if (empty($labels)) {
            return $this->getEmptyData();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos por categoría',
                    'data' => $values,
                    'backgroundColor' => $colors,
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getEmptyData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Sin datos',
                    'data' => [1],
                    'backgroundColor' => ['#e5e7eb'],
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => ['No hay ingresos registrados'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'padding' => 20,
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'font' => [
                            'size' => 10,
                        ],
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => function ($context) {
                            $label = $context->label ?? '';
                            $value = $context->raw ?? 0;
                            $total = array_sum($context->dataset->data ?? [0]);
                            $percentage = $total > 0 ? round(($value / $total) * 100, 1) : 0;

                            $currency = Currency::where('is_base', true)->first();
                            $symbol = $currency?->symbol ?? 'Bs.S';

                            return "{$label}: {$symbol} " . number_format($value, 2, ',', '.') . " ({$percentage}%)";
                        },
                    ],
                ],
            ],
            'cutout' => '60%',
        ];
    }
}