<?php

namespace App\Filament\Resources\ImportSessions\Pages;

use App\Filament\Resources\ImportSessions\ImportSessionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditImportSession extends EditRecord
{
    protected static string $resource = ImportSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    // ✅ Cambiar el texto y comportamiento del botón de Guardar
    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Actualizar Información'); // O el texto que desees para guardar los datos
    }

    // ✅ Cambiar el texto del botón Cancelar
    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Cancelar'); // O el texto que desees para regresar
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
