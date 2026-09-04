<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;
    protected static ?string $title = 'Listado de Categorías';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
            ->label('Crear categoría')
                ->color('success')
                ->icon(Heroicon::PlusCircle)
                ->createAnother(false)
                ->modalHeading('Crear Categoría')
                ->modalSubmitActionLabel('Crear Nueva Categoría')
                ->modalCancelActionLabel('Cancelar')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Categoría creada')
                        ->body('La categoría se ha creado correctamente.')
                ),
        ];
    }
}
