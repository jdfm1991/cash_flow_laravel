<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AccountsExport implements FromArray, WithHeadings, WithStyles
{
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
                $item['account_alias'] ?? '',
                $item['account_number'] ?? '',
                $item['bank_name'] ?? '',
                $item['currency'] ?? '',
                $item['initial_balance'] ?? 0,
                $item['income'] ?? 0,
                $item['expense'] ?? 0,
                $item['final_balance'] ?? 0,
                $item['net_change'] ?? 0,
            ];
        }
        return $rows;
    }

    public function headings(): array
    {
        return [
            'Cuenta',
            'Número',
            'Banco',
            'Moneda',
            'Saldo Inicial',
            'Ingresos',
            'Egresos',
            'Saldo Final',
            'Cambio Neto',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}