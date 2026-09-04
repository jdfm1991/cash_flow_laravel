<?php

namespace App\Filament\Resources\ExternalConnections\Pages;

use App\Filament\Resources\ExternalConnections\ExternalConnectionResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditExternalConnection extends EditRecord
{
    protected static string $resource = ExternalConnectionResource::class;

    protected static ?string $title = 'Editar Información De Conexión';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // ✅ Si se proporciona nueva contraseña, encriptarla
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = \Illuminate\Support\Facades\Crypt::encryptString($data['password']);
        } else {
            // ✅ Mantener la contraseña actual
            unset($data['password']);
        }

        return $data;
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
            ->title('Conexión actualizado')
            ->body('La conexión se ha actualizado correctamente.');
    }
}
