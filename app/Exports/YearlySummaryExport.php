<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class YearlySummaryExport implements FromArray, WithHeadings, WithStyles
{
    use Exportable;  // ✅ Agregar

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->data as $item) {
            $rows[] = [
                $item['year'] ?? '',
                $item['income'] ?? 0,
                $item['expense'] ?? 0,
                $item['net'] ?? 0,
                $item['income_count'] ?? 0,
                $item['expense_count'] ?? 0,
            ];
        }
        return $rows;
    }

    public function headings(): array
    {
        return [
            'Año',
            'Ingresos',
            'Egresos',
            'Neto',
            '# Ingresos',
            '# Egresos',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
