<?php

namespace App\Filament\Actions;

use App\Services\ExchangeRateService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ImportRatesFromTransactionsAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'importFromTransactions')
            ->label('Importar desde Transacciones')
            ->icon(Heroicon::ArrowDownTray)
            ->color('primary')
            ->modalHeading('Importar Tasas desde Transacciones')
            ->modalSubmitActionLabel('Importar Ahora')
            ->modalCancelActionLabel('Cancelar')
            ->modalContent(function () {
                // ✅ Obtener preview al abrir el modal
                $service = app(ExchangeRateService::class);
                $preview = $service->previewImportFromTransactions();

                return view('filament.actions.import-rates-preview', [
                    'preview' => $preview,
                ]);
            })
            ->action(function () {
                $service = app(ExchangeRateService::class);

                try {
                    $stats = $service->importFromTransactions();

                    if ($stats['success']) {
                        Notification::make()
                            ->success()
                            ->title('✅ Importación completada')
                            ->body(
                                "Total encontradas: {$stats['total_found']}\n" .
                                "Importadas: {$stats['imported']}\n" .
                                "Omitidas (ya existían): {$stats['skipped']}\n" .
                                "Errores: {$stats['errors']}"
                            )
                            ->persistent()
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('❌ Error en la importación')
                            ->body(implode("\n", $stats['details']))
                            ->persistent()
                            ->send();
                    }
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error en la importación')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }
}