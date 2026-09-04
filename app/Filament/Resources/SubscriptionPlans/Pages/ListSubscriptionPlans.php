<?php

namespace App\Filament\Resources\SubscriptionPlans\Pages;

use App\Filament\Resources\SubscriptionPlans\SubscriptionPlanResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSubscriptionPlans extends ListRecords
{
    protected static string $resource = SubscriptionPlanResource::class;
    protected static ?string $title = 'Listado de Planes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Crear plan')
                ->color('success')
                ->icon(Heroicon::PlusCircle)
                ->createAnother(false)
                ->modalHeading('Crear Plan de Suscripción')
                ->modalSubmitActionLabel('Crear Nuevo Plan')
                ->modalCancelActionLabel('Cancelar')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Plan creado')
                        ->body('El plan de suscripción se ha creado correctamente.')
                ),
        ];
    }
}
