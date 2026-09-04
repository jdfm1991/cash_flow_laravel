<?php

namespace App\Filament\Resources\ImportSessions\Pages;

use App\Filament\Resources\ImportSessions\ImportSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListImportSessions extends ListRecords
{
    protected static string $resource = ImportSessionResource::class;
    protected static ?string $title = 'Historial de Importaciones';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva Importación')
                ->color('success')
                ->icon(Heroicon::PlusCircle)
                ->createAnother(false)
                ->modalHeading('Configurar Importación')
                ->modalSubmitActionLabel('Iniciar Importación')
                ->modalCancelActionLabel('Cancelar')
                ->model(ImportSessionResource::getModel()),
        ];
    }
}