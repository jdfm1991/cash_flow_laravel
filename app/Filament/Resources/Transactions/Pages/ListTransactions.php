<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // En el dashboard o en una página
            Action::make('reporte_flujo_caja')
                ->label('📊 Reporte de Flujo de Caja')
                ->color('primary')
                ->url(route('reports.cash-flow.download', [
                    'start_date' => now()->startOfMonth()->toDateString(),
                    'end_date' => now()->toDateString(),
                ]))
                ->openUrlInNewTab(true),

            CreateAction::make()->label('Nueva transacción')
                ->color('success')
                ->icon(Heroicon::PlusCircle)
                ->createAnother(false)
                ->modalHeading('Registrar Transacción')
                ->modalSubmitActionLabel('Registrar')
                ->modalCancelActionLabel('Cancelar')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Transacción registrada')
                        ->body('La transacción se ha registrado correctamente.')
                ),
        ];
    }
}
