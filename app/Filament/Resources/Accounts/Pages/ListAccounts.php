<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;
    protected static ?string $title = 'Listado de Cuentas Contables';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear cuenta')
                ->color('success')
                ->icon(Heroicon::PlusCircle)
                ->createAnother(false)
                ->modalHeading('Crear Cuenta Contable')
                ->modalSubmitActionLabel('Crear Nueva Cuenta')
                ->modalCancelActionLabel('Cancelar')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Cuenta creada')
                        ->body('La cuenta contable se ha creado correctamente.')
                ),
        ];
    }
}
