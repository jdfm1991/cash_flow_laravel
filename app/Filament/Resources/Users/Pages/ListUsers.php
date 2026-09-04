<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
    protected static ?string $title = 'Listado de Usuarios';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Crear usuario')
                ->color('success')
                ->icon(Heroicon::PlusCircle)
                ->createAnother(false)
                ->modalHeading('Crear Usuario')
                ->modalSubmitActionLabel('Crear Nuevo Usuario')
                ->modalCancelActionLabel('Cancelar')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Usuario creado')
                        ->body('El usuario se ha creado correctamente.')
                ),
        ];
    }
}
