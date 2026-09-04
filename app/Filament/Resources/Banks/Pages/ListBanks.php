<?php

namespace App\Filament\Resources\Banks\Pages;

use App\Filament\Resources\Banks\BankResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListBanks extends ListRecords
{
    protected static string $resource = BankResource::class;
     protected static ?string $title = 'Listado de bancos';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear banco')
                ->color('success')
                ->icon(Heroicon::PlusCircle)
                ->createAnother(false)
                ->modalHeading('Crear banco')
                ->modalSubmitActionLabel('Crear Nuevo Banco')
                ->modalCancelActionLabel('Cancelar')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Banco creado')
                        ->body('El banco se ha creado correctamente.')
                ),
        ];
    }
}
