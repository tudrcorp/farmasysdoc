<?php

namespace App\Filament\Resources\Hr\Employees\Tables;

use App\Models\Employee;
use App\Services\Hr\HrBcvRateResolver;
use App\Services\Hr\HrUsdVesConverter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('')
                    ->disk('public')
                    ->visibility('public')
                    ->circular()
                    ->imageSize(40)
                    ->defaultImageUrl(fn (Employee $record): string => $record->tableAvatarPlaceholderDataUri())
                    ->extraImgAttributes(fn (Employee $record): array => [
                        'alt' => $record->fullName(),
                        'class' => 'fi-hr-employee-table-avatar',
                    ])
                    ->extraAttributes([
                        'class' => 'fi-hr-employee-table-avatar-cell',
                    ]),
                TextColumn::make('full_name')
                    ->label('Empleado')
                    ->state(fn (Employee $record): string => $record->fullName())
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(query: function ($query, string $direction) {
                        $query->orderBy('last_name', $direction)->orderBy('first_name', $direction);
                    })
                    ->weight('medium'),
                TextColumn::make('national_id')
                    ->label('Cédula')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->label('Email')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('monthly_salary_usd')
                    ->label('Sueldo mensual')
                    ->alignEnd()
                    ->formatStateUsing(function ($state): string {
                        $usd = number_format((float) $state, 2, ',', '.');
                        $rate = app(HrBcvRateResolver::class)->resolveForDate(now());
                        if ($rate === null) {
                            return "US$ {$usd}";
                        }
                        $ves = number_format(HrUsdVesConverter::toVes((float) $state, $rate), 2, ',', '.');

                        return "US$ {$usd}\nBs {$ves}";
                    })
                    ->wrap(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('last_name')
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Activo')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos')
                    ->placeholder('Todos'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
