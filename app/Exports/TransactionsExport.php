<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionsExport implements FromArray, WithHeadings, WithStyles
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
        $transactions = $this->data['transactions'] ?? [];

        foreach ($transactions as $item) {
            $rows[] = [
                $item['date'] ?? '',
                $item['type_label'] ?? '',
                $item['description'] ?? '',
                $item['account'] ?? '',
                $item['category'] ?? '',
                $item['amount'] ?? 0,
                $item['currency'] ?? '',
                $item['reference'] ?? '',
            ];
        }
        return $rows;
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Tipo',
            'Descripción',
            'Cuenta Contable',
            'Categoría',
            'Monto',
            'Moneda',
            'Referencia',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
