<?php

namespace App\Filament\Resources\ExternalConnections\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExternalConnectionsTable
{
    
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nombre'),

                TextColumn::make('company.name')
                    ->searchable()
                    ->sortable()
                    ->label('Empresa'),

                TextColumn::make('host')
                    ->searchable()
                    ->sortable()
                    ->label('Host'),

                TextColumn::make('db_name')
                    ->searchable()
                    ->sortable()
                    ->label('Base de datos'),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'migration' => 'primary',
                        'replication' => 'info',
                        'integration' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'migration' => 'Migración',
                        'replication' => 'Replicación',
                        'integration' => 'Integración',
                        default => ucfirst($state),
                    }),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('last_sync_at')
                    ->label('Última sincronización')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Empresa'),

                SelectFilter::make('type')
                    ->options([
                        'migration' => 'Migración',
                        'replication' => 'Replicación',
                        'integration' => 'Integración',
                    ]),

                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Activas',
                        '0' => 'Inactivas',
                    ]),
            ])
            ->recordActions([
                // ✅ Botón para probar conexión
                Action::make('test')
                    ->label('Probar')
                    ->color('info')
                    ->icon(Heroicon::Wifi)
                    ->action(function ($record) {
                        try {
                            // ✅ Obtener el modelo completo desde la base de datos (sin $hidden)
                            $connection = \App\Models\ExternalConnection::withTrashed()
                                ->where('id', $record->id)
                                ->first();

                            if (!$connection) {
                                Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body('Conexión no encontrada.')
                                    ->send();
                                return;
                            }

                            // ✅ Construir los datos para la prueba
                            $data = [
                                'host' => $connection->host,
                                'port' => $connection->port,
                                'db_name' => $connection->db_name,
                                'username' => $connection->username,
                                'password' => $connection->getDecryptedPassword(),
                            ];

                            $service = app(\App\Services\ExternalConnectionService::class);
                            $result = $service->testConnection($data);

                            if ($result['success']) {
                                Notification::make()
                                    ->success()
                                    ->title('✅ Conexión exitosa')
                                    ->body('La conexión a la base de datos externa funciona correctamente.')
                                    ->send();
                            } else {
                                Notification::make()
                                    ->danger()
                                    ->title('❌ Error de conexión')
                                    ->body($result['message'])
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->label('Editar')
                    ->color('primary')
                    ->icon(Heroicon::PencilSquare)
                    ->modalHeading('Editar Conexión Externa')
                    ->modalSubmitActionLabel('Actualizar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Conexión actualizada')
                            ->body('La conexión externa se ha actualizado correctamente.')
                    ),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon(Heroicon::Trash)
                    ->modalHeading('Eliminar Conexión')
                    ->modalDescription('¿Estás seguro de eliminar esta conexión externa?')
                    ->modalSubmitActionLabel('Sí, Eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Conexión eliminada')
                            ->body('La conexión externa se ha eliminado correctamente.')
                    )
                    ->before(function ($record) {
                        if ($record->migrationLogs()->count() > 0) {
                            throw new \Exception('No se puede eliminar la conexión porque tiene migraciones asociadas.');
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->migrationLogs()->count() > 0) {
                                    throw new \Exception("No se puede eliminar la conexión '{$record->name}' porque tiene migraciones asociadas.");
                                }
                            }
                        }),
                ]),
            ]);
    }
}
