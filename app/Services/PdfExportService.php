<?php

namespace App\Services;

use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\Enums\Format;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Log;

class PdfExportService
{
    /**
     * Generar PDF con gráficos
     *
     * @param string $view Nombre de la vista Blade
     * @param array $data Datos para la vista (debe incluir chart_labels, chart_income, chart_expense, chart_net)
     * @param string $filename Nombre del archivo (sin extensión)
     * @return BinaryFileResponse
     */
    public function generatePdf(string $view, array $data, string $filename): BinaryFileResponse
    {
        $chartImagePath = null;
        $tempFiles = [];

        try {
            // ✅ 1. Generar gráfico de líneas usando ChartImageService
            $chartService = app(ChartImageService::class);

            if (!empty($data['chart_labels']) && !empty($data['chart_income'])) {
                $chartImagePath = $chartService->generateLineChart(
                    labels: $data['chart_labels'],
                    incomeData: $data['chart_income'],
                    expenseData: $data['chart_expense'] ?? [],
                    netData: $data['chart_net'] ?? [],
                    title: 'Evolucion Mensual de Ingresos vs Egresos',
                    width: 700,
                    height: 350
                );

                if ($chartImagePath) {
                    $data['chart_image_path'] = $chartImagePath;
                    $tempFiles[] = $chartImagePath;
                }
            }

            // ✅ 2. Generar gráfico de barras opcional (si hay datos y se desea)
            // Este es un gráfico adicional que aporta valor al análisis
            if (!empty($data['chart_labels']) && !empty($data['chart_income']) && count($data['chart_labels']) > 1) {
                $barChartPath = $chartService->generateBarChart(
                    labels: $data['chart_labels'],
                    incomeData: $data['chart_income'],
                    expenseData: $data['chart_expense'] ?? [],
                    title: 'Comparativa Mensual'
                );

                if ($barChartPath) {
                    $data['bar_chart_image_path'] = $barChartPath;
                    $tempFiles[] = $barChartPath;
                }
            }

            // ✅ 3. Generar el PDF
            $tempPath = storage_path("app/temp/pdf/{$filename}.pdf");
            $tempDir = dirname($tempPath);

            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            config(['pdf.default' => 'dompdf']);

            Pdf::view($view, $data)
                ->format(Format::A4)
                ->margins(15, 15, 15, 15) // Márgenes un poco más amplios para mejor presentación
                ->save($tempPath);

            // ✅ 4. Preparar respuesta y limpiar archivos temporales
            $response = response()->download($tempPath, "{$filename}.pdf")
                ->deleteFileAfterSend(true);

            // Los archivos temporales de imágenes se eliminarán después de la descarga
            // usando un shutdown function para asegurar la limpieza
            register_shutdown_function(function () use ($tempFiles) {
                foreach ($tempFiles as $file) {
                    if (file_exists($file)) {
                        @unlink($file);
                    }
                }
            });

            return $response;
        } catch (\Exception $e) {
            // ✅ En caso de error, limpiar archivos temporales
            foreach ($tempFiles as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }

            Log::error('Error generando PDF: ' . $e->getMessage(), [
                'view' => $view,
                'filename' => $filename,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * ✅ NUEVO: Generar PDF sin gráficos (para Transaction Report, etc.)
     */
    public function generatePdfWithoutCharts(string $view, array $data, string $filename): BinaryFileResponse
    {
        try {
            // ✅ AUMENTAR MEMORIA Y TIEMPO DE EJECUCIÓN
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 300); // 5 minutos

            // ✅ REDUCIR LA CANTIDAD DE DATOS si es necesario
            // Si hay más de 500 transacciones, limitar a las más recientes
            if (isset($data['transactions']) && count($data['transactions']) > 500) {
                $data['transactions'] = array_slice($data['transactions'], 0, 500);
                $data['truncated'] = true;
                $data['truncated_message'] = '⚠️ Mostrando solo las 500 transacciones más recientes por límite de memoria.';
            }
            // ✅ 1. Generar el PDF sin gráficos
            $tempPath = storage_path("app/temp/pdf/{$filename}.pdf");
            $tempDir = dirname($tempPath);

            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            config(['pdf.default' => 'dompdf']);

            Pdf::view($view, $data)
                ->format(Format::A4)
                ->margins(15, 15, 15, 15)
                ->save($tempPath);

            // ✅ 2. Preparar respuesta
            $response = response()->download($tempPath, "{$filename}.pdf")
                ->deleteFileAfterSend(true);

            return $response;
        } catch (\Exception $e) {
            Log::error('Error generando PDF sin gráficos: ' . $e->getMessage(), [
                'view' => $view,
                'filename' => $filename,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Método legacy para compatibilidad con código existente
     * 
     * @deprecated Use generatePdf() directamente
     */
    public function generateSimplePdf(string $view, array $data, string $filename): BinaryFileResponse
    {
        return $this->generatePdf($view, $data, $filename);
    }

    /**
     * Generar PDF sin gráficos (para reportes simples)
     * 
     * @param string $view Nombre de la vista Blade
     * @param array $data Datos para la vista
     * @param string $filename Nombre del archivo (sin extensión)
     * @return BinaryFileResponse
     */
    public function generateSimplePdfWithoutCharts(string $view, array $data, string $filename): BinaryFileResponse
    {
        try {
            $tempPath = storage_path("app/temp/pdf/{$filename}.pdf");
            $tempDir = dirname($tempPath);

            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            Pdf::view($view, $data)
                ->format(Format::A4)
                ->margins(15, 15, 15, 15)
                ->save($tempPath);

            return response()->download($tempPath, "{$filename}.pdf")
                ->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error generando PDF simple: ' . $e->getMessage(), [
                'view' => $view,
                'filename' => $filename,
            ]);
            throw $e;
        }
    }

    /**
     * Limpiar archivos temporales antiguos (más de 1 hora)
     * Este método puede ejecutarse programáticamente o mediante un comando
     */
    public function cleanOldTemporaryFiles(): int
    {
        $deletedCount = 0;
        $tempDirs = [
            storage_path('app/temp/pdf'),
            storage_path('app/temp/charts'),
        ];

        $expirationTime = time() - 3600; // 1 hora

        foreach ($tempDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $files = glob($dir . '/*.*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $expirationTime) {
                    if (@unlink($file)) {
                        $deletedCount++;
                    }
                }
            }
        }

        return $deletedCount;
    }
}
