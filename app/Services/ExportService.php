<?php

namespace App\Services;

use App\Exports\CategoriesExport;
use App\Exports\TransactionsExport;
use App\Exports\CashFlowExport;
use App\Exports\AccountsExport;
use App\Exports\MonthlyComparisonExport;
use App\Exports\YearlySummaryExport;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportService
{

    /**
     * Generar PDF desde una vista
     */
    protected function generatePdf(string $view, array $data, string $filename): BinaryFileResponse
    {
        $html = view($view, $data)->render();

        $pdf = Pdf::html($html)
            ->format('A4')
            ->landscape(false)
            ->margins(10, 10, 10, 10)
            ->save(storage_path("app/public/temp_{$filename}.pdf"));

        return response()->download(
            storage_path("app/public/temp_{$filename}.pdf"),
            "{$filename}.pdf"
        )->deleteFileAfterSend(true);
    }

    /**
     * Exportar reporte de flujo de caja a PDF
     */
    public function exportCashFlow(array $data, string $format): BinaryFileResponse
    {
        $filename = 'flujo_caja_' . now()->format('Y-m-d_H-i-s');

        if ($format === 'pdf') {
            $viewData = array_merge($data, [
                'title' => 'Reporte de Flujo de Caja',
                'companyName' => auth()->user()?->currentCompany?->name ?? 'Empresa',
            ]);

            return $this->generatePdf('reports.cash-flow', $data, $filename);
        }

        // Excel (usando Maatwebsite Excel)
        return Excel::download(new CashFlowExport($data), $filename . '.xlsx');
    }

    /**
     * Exportar reporte de transacciones a PDF
     */
    public function exportTransactions(array $data, string $format): BinaryFileResponse
    {
        $filename = 'transacciones_' . now()->format('Y-m-d_H-i-s');

        if ($format === 'pdf') {
            $viewData = array_merge($data, [
                'title' => 'Reporte de Transacciones',
                'companyName' => auth()->user()?->currentCompany?->name ?? 'Empresa',
            ]);

            return $this->generatePdf('reports.transactions', $viewData, $filename);
        }

        // Excel o CSV
        return Excel::download(new TransactionsExport($data), $filename . '.xlsx');
    }

    public function exportCategories(array $data, string $format): BinaryFileResponse
    {
        $export = new CategoriesExport($data);
        $filename = 'categorias_' . now()->format('Y-m-d_H-i-s');

        return $this->download($export, $filename, $format);
    }

    public function exportAccounts(array $data, string $format): BinaryFileResponse
    {
        $export = new AccountsExport($data);
        $filename = 'cuentas_' . now()->format('Y-m-d_H-i-s');

        return $this->download($export, $filename, $format);
    }

    public function exportMonthlyComparison(array $data, string $format): BinaryFileResponse
    {
        $filename = 'comparativo_mensual_' . now()->format('Y-m-d_H-i-s');

        if ($format === 'pdf') {
            return $this->generatePdf('reports.monthly-comparison', $data, $filename);
        }

        return Excel::download(new MonthlyComparisonExport($data), $filename . '.xlsx');
    }

    public function exportYearlySummary(array $data, string $format): BinaryFileResponse
    {
        $filename = 'resumen_anual_' . now()->format('Y-m-d_H-i-s');

        if ($format === 'pdf') {
            return $this->generatePdf('reports.yearly-summary', $data, $filename);
        }

        return Excel::download(new YearlySummaryExport($data), $filename . '.xlsx');
    }

    protected function download($export, string $filename, string $format): BinaryFileResponse
    {
        $extension = match ($format) {
            'excel' => 'xlsx',
            'csv' => 'csv',
            'pdf' => 'pdf',
            default => 'xlsx',
        };

        $writer = match ($format) {
            'excel' => \Maatwebsite\Excel\Excel::XLSX,
            'csv' => \Maatwebsite\Excel\Excel::CSV,
            'pdf' => \Maatwebsite\Excel\Excel::DOMPDF,
            default => \Maatwebsite\Excel\Excel::XLSX,
        };

        return Excel::download($export, $filename . '.' . $extension, $writer);
    }
}
