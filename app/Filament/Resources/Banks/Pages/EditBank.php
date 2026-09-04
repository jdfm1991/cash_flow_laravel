<?php

namespace App\Filament\Resources\Banks\Pages;

use App\Filament\Resources\Banks\BankResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditBank extends EditRecord
{
    protected static string $resource = BankResource::class;

    protected static ?string $title = 'Editar Información Del Banco';

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

    // ✅ Después de guardar, volver a la lista
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // ✅ Notificación de éxito
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Banco actualizado')
            ->body('El banco se ha actualizado correctamente.');
    }
}
