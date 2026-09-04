<?php

namespace App\Filament\Resources\ImportSessions\Tables;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

class ImportSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('source')
                    ->label('Fuente')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'excel' => 'success',
                        'migration' => 'primary',
                        'csv' => 'info',
                        'pdf' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'excel' => 'Excel',
                        'migration' => 'Migración',
                        'csv' => 'CSV',
                        'pdf' => 'PDF',
                        default => ucfirst($state),
                    }),

                TextColumn::make('file_name')
                    ->label('Archivo')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ PROGRESS COLUMN - Versión corregida
                TextColumn::make('progress')
                    ->label('Progreso')
                    ->getStateUsing(fn($record) => $record->getProgressAttribute() . '%')
                    ->color(fn($record) => match ($record->status) {
                        'completed' => 'success',
                        'processing' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending' => '⏳ Pendiente',
                        'processing' => '🔄 Procesando',
                        'completed' => '✅ Completado',
                        'failed' => '❌ Fallido',
                        default => ucfirst($state),
                    }),

                TextColumn::make('total_rows')
                    ->label('Total')
                    ->sortable(),

                TextColumn::make('processed_rows')
                    ->label('Procesadas')
                    ->sortable(),

                TextColumn::make('duplicated_rows')
                    ->label('Duplicadas')
                    ->sortable()
                    ->color('warning'),

                TextColumn::make('error_rows')
                    ->label('Errores')
                    ->sortable()
                    ->color('danger'),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Fuente')
                    ->options([
                        'excel' => 'Excel',
                        'migration' => 'Migración',
                        'csv' => 'CSV',
                        'pdf' => 'PDF',
                    ]),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => '⏳ Pendiente',
                        'processing' => '🔄 Procesando',
                        'completed' => '✅ Completado',
                        'failed' => '❌ Fallido',
                    ]),

                SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Usuario'),

                SelectFilter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('start_date')
                            ->label('Desde'),
                        \Filament\Forms\Components\DatePicker::make('end_date')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['start_date'] ?? null, fn($q, $date) =>
                            $q->whereDate('created_at', '>=', $date))
                            ->when($data['end_date'] ?? null, fn($q, $date) =>
                            $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('classify')
                    ->label('Clasificar')
                    ->color('warning')
                    ->icon(Heroicon::AdjustmentsHorizontal)
                    ->url(fn($record) => \App\Filament\Resources\ImportSessions\ImportSessionResource::getUrl('classify', ['record' => $record]))
                    ->visible(
                        fn($record) =>
                        $record->status === 'completed' &&
                            $record->total_rows > 0 &&
                            $record->total_rows == $record->processed_rows &&
                            $record->processed_rows > 0
                    ),
            ])
            ->toolbarActions([
                /* BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]), */]);
    }
}
