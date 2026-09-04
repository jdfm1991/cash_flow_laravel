<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecentTransactions extends TableWidget
{

    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 3;
    protected static ?string $pollingInterval = '60s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->when(auth()->user()?->current_company_id, function ($query) {
                        return $query->where('company_id', auth()->user()->current_company_id);
                    })
                    ->orderBy('date', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn($state) => $state === 'income' ? 'success' : 'danger')
                    ->formatStateUsing(fn($state) => $state === 'income' ? '💰 Ingreso' : '💸 Egreso'),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money(fn() => \App\Models\Currency::where('is_base', true)->first()?->code ?? 'VES')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->description),

                TextColumn::make('account.name')
                    ->label('Cuenta contable')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('bankAccount.alias')
                    ->label('Cuenta bancaria')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'income' => 'Ingresos',
                        'expense' => 'Egresos',
                    ])
                    ->label('Tipo'),
            ])
            ->defaultSort('date', 'desc');
    }
}
