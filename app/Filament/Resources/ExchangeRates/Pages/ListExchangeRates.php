<?php

namespace App\Filament\Resources\ExchangeRates\Pages;

use App\Filament\Actions\ImportRatesFromTransactionsAction;
use App\Filament\Resources\ExchangeRates\ExchangeRateResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListExchangeRates extends ListRecords
{
    protected static string $resource = ExchangeRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportRatesFromTransactionsAction::make(),
            CreateAction::make()
                ->label('Crear tasa')
                ->color('success')
                ->icon(Heroicon::PlusCircle)
                ->createAnother(false)
                ->modalHeading('Crear Tasa de Cambio')
                ->modalSubmitActionLabel('Crear Nueva Tasa')
                ->modalCancelActionLabel('Cancelar')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Tasa creada')
                        ->body('La tasa de cambio se ha creado correctamente.')
                ),
        ];
    }
}
