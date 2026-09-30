<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\TransactionCurrencyService;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Asignar company_id y user_id
        $data['company_id'] = auth()->user()?->current_company_id;
        $data['user_id'] = auth()->id();

        // ✅ Calcular automáticamente el monto convertido
        if (!empty($data['amount']) && !empty($data['currency_id']) && !empty($data['date'])) {
            $service = app(TransactionCurrencyService::class);
            $result = $service->calculateConversion(
                (int) $data['currency_id'],
                (float) $data['amount'],
                $data['date']
            );

            if ($result['success']) {
                $data['amount_converted'] = $result['amount_converted'];
                $data['exchange_rate'] = $result['exchange_rate'];
                $data['exchange_rate_id'] = $result['exchange_rate_id'];
            } else {
                $data['amount_converted'] = $data['amount'];
                $data['exchange_rate'] = 1;
            }
        }

        return $data;
    }
}