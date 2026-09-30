<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\TransactionCurrencyService;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // ✅ Recalcular el monto convertido si cambió algún campo relevante
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
            }
        }

        return $data;
    }
}