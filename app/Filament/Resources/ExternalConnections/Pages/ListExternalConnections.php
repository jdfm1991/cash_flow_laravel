<?php

namespace App\Filament\Resources\ExternalConnections\Pages;

use App\Filament\Resources\ExternalConnections\ExternalConnectionResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListExternalConnections extends ListRecords
{
    protected static string $resource = ExternalConnectionResource::class;
    protected static ?string $title = 'Conexiones Externas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva Conexión')
                ->color('success')
                ->icon(Heroicon::PlusCircle)
                ->createAnother(false)
                ->modalHeading('Configurar Conexión Externa')
                ->modalSubmitActionLabel('Crear Conexión')
                ->modalCancelActionLabel('Cancelar')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Conexión creada')
                        ->body('La conexión externa se ha creado correctamente.')
                ),
        ];
    }
}