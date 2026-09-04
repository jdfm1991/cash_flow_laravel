<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\Exportable;

class CashFlowExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    use Exportable;  // ✅ Agregar

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Convertir datos a formato de filas para Excel
     */
    public function array(): array
    {
        $rows = [];

        // ✅ 1. Filas de resumen
        $totals = $this->data['totals'] ?? [];
        $period = $this->data['period'] ?? [];

        $rows[] = ['RESUMEN DEL PERÍODO'];
        $rows[] = ['Período', $period['start'] ?? '', 'al', $period['end'] ?? ''];
        $rows[] = ['Días', $period['days'] ?? 0];
        $rows[] = [''];
        $rows[] = ['Total Ingresos', $totals['income'] ?? 0];
        $rows[] = ['Total Egresos', $totals['expense'] ?? 0];
        $rows[] = ['Flujo Neto', $totals['net'] ?? 0];
        $rows[] = ['N° Transacciones', $totals['transaction_count'] ?? 0];
        $rows[] = [''];

        // ✅ 2. Ingresos por categoría
        $rows[] = ['INGRESOS POR CATEGORÍA'];
        $rows[] = ['Categoría', 'Total', 'Cantidad'];
        $incomeByCategory = $this->data['income_by_category'] ?? [];
        foreach ($incomeByCategory as $item) {
            $rows[] = [
                $item['category'] ?? 'Sin categoría',
                $item['total'] ?? 0,
                $item['count'] ?? 0,
            ];
        }

        $rows[] = [''];

        // ✅ 3. Egresos por categoría
        $rows[] = ['EGRESOS POR CATEGORÍA'];
        $rows[] = ['Categoría', 'Total', 'Cantidad'];
        $expenseByCategory = $this->data['expense_by_category'] ?? [];
        foreach ($expenseByCategory as $item) {
            $rows[] = [
                $item['category'] ?? 'Sin categoría',
                $item['total'] ?? 0,
                $item['count'] ?? 0,
            ];
        }

        $rows[] = [''];

        // ✅ 4. Evolución diaria (opcional - limitar a 31 días)
        $rows[] = ['EVOLUCIÓN DIARIA'];
        $rows[] = ['Fecha', 'Ingresos', 'Egresos', 'Neto', 'Balance', 'Transacciones'];
        $dailyEvolution = $this->data['daily_evolution'] ?? [];
        $dailyEvolution = array_slice($dailyEvolution, 0, 31); // Limitar a 31 días
        foreach ($dailyEvolution as $day) {
            $rows[] = [
                $day['date'] ?? '',
                $day['income'] ?? 0,
                $day['expense'] ?? 0,
                $day['net'] ?? 0,
                $day['balance'] ?? 0,
                $day['transactions'] ?? 0,
            ];
        }

        $rows[] = [''];

        // ✅ 5. Resumen de cuentas
        $rows[] = ['RESUMEN DE CUENTAS'];
        $rows[] = ['Cuenta', 'Banco', 'Saldo Inicial', 'Ingresos', 'Egresos', 'Saldo Final', 'Cambio Neto'];
        $accountSummary = $this->data['account_summary'] ?? [];
        foreach ($accountSummary as $account) {
            $rows[] = [
                $account['account_alias'] ?? 'N/A',
                $account['bank_name'] ?? 'N/A',
                $account['initial_balance'] ?? 0,
                $account['income'] ?? 0,
                $account['expense'] ?? 0,
                $account['final_balance'] ?? 0,
                $account['net_change'] ?? 0,
            ];
        }

        return $rows;
    }

    /**
     * Encabezados de columnas
     */
    public function headings(): array
    {
        return [];
    }

    /**
     * Estilos para el Excel
     */
    public function styles(Worksheet $sheet)
    {
        // ✅ Aplicar estilos a títulos en negrita
        $sheet->getStyle('A1:A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
            'font' => ['color' => ['rgb' => 'FFFFFF']],
        ]);

        // ✅ Títulos de secciones
        $sectionTitles = [1, 10, 18, 31, 38];
        foreach ($sectionTitles as $row) {
            $sheet->getStyle("A{$row}:A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
            ]);
        }

        // ✅ Negrita en encabezados de tablas
        $headerRows = [3, 11, 13, 19, 21, 32, 34, 39, 41];
        foreach ($headerRows as $row) {
            $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F3F4F6'],
                ],
            ]);
        }

        // ✅ Formato de números
        $numberRows = range(5, 999);
        foreach ($numberRows as $row) {
            $sheet->getStyle("B{$row}:G{$row}")->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }

        // ✅ Bordes
        $sheet->getStyle('A1:G' . $sheet->getHighestRow())
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // ✅ Alineación
        $sheet->getStyle('A:G')->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);

        return [];
    }

    /**
     * Ancho de columnas
     */
    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 18,
            'C' => 18,
            'D' => 18,
            'E' => 18,
            'F' => 18,
            'G' => 18,
        ];
    }
}
