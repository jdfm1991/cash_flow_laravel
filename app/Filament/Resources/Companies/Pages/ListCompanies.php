<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;
    protected static ?string $title = 'Listado de Empresas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Crear empresa')
                ->color('success')
                ->icon(Heroicon::PlusCircle)
                ->createAnother(false)
                ->modalHeading('Crear Empresa')
                ->modalSubmitActionLabel('Crear Nueva Empresa')
                ->modalCancelActionLabel('Cancelar')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Empresa creada')
                        ->body('La empresa se ha creado correctamente.')
                ),
        ];
    }
}
