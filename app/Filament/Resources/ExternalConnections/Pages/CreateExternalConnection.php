<?php

namespace App\Filament\Resources\ExternalConnections\Pages;

use App\Filament\Resources\ExternalConnections\ExternalConnectionResource;
use App\Services\ExternalConnectionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateExternalConnection extends CreateRecord
{
    protected static string $resource = ExternalConnectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ✅ Usar el servicio para crear (encripta la contraseña)
        $service = app(ExternalConnectionService::class);
        $connection = $service->create($data);
        
        // ✅ Redirigir a la lista
        redirect()->route('filament.admin.resources.external-connections.index');
        
        // ✅ Prevenir la creación automática
        $this->halt();
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}