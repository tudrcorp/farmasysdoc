<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use App\Support\Audit\AuditLogEventPresentation;
use App\Support\Filament\AuditLogIosDetailHtml;
use App\Support\Filament\SlideoverModalScrollFix;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->emptyStateHeading('Sin registros de auditoría')
            ->emptyStateDescription('Cuando haya actividad en el panel o cambios en datos auditados, aparecerán aquí.')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Fecha / hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('uid')
                    ->label('UID')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('UID copiado')
                    ->limit(18)
                    ->tooltip(fn (AuditLog $record): ?string => filled($record->uid) ? (string) $record->uid : null)
                    ->placeholder('—'),
                TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? AuditLogEventPresentation::label($state)
                        : '—')
                    ->color(fn (?string $state): string => filled($state)
                        ? AuditLogEventPresentation::badgeColor($state)
                        : 'gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user_email')
                    ->label('Usuario')
                    ->description(fn (AuditLog $record): ?string => $record->user_id !== null ? 'ID: '.$record->user_id : null)
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('auditable_type')
                    ->label('Entidad')
                    ->formatStateUsing(fn (?string $state): string => $state !== null && $state !== ''
                        ? class_basename($state)
                        : '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('auditable_type', 'like', '%'.$search.'%');
                    })
                    ->toggleable(),
                TextColumn::make('auditable_id')
                    ->label('ID registro')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('auditable_label')
                    ->label('Referencia')
                    ->limit(40)
                    ->tooltip(fn (AuditLog $record): ?string => $record->auditable_label)
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50)
                    ->tooltip(fn (AuditLog $record): ?string => $record->description)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('route_name')
                    ->label('Ruta')
                    ->limit(36)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('panel_id')
                    ->label('Panel')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Tipo de evento')
                    ->options(AuditLogEventPresentation::filterOptions()),
                TernaryFilter::make('with_auditable')
                    ->label('Registro enlazado')
                    ->placeholder('Todos')
                    ->trueLabel('Con entidad')
                    ->falseLabel('Sin entidad')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('auditable_type')
                            ->whereNotNull('auditable_id'),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $q): void {
                            $q->whereNull('auditable_type')
                                ->orWhereNull('auditable_id');
                        }),
                    ),
                Filter::make('created_range')
                    ->label('Rango de fechas')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Desde')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Hasta')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['from'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('created_at', '>=', (string) $data['from'])
                            )
                            ->when(
                                filled($data['until'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('created_at', '<=', (string) $data['until'])
                            );
                    }),
            ])
            ->recordActions([
                Action::make('viewAuditDetail')
                    ->label('Ver')
                    ->icon(Heroicon::Eye)
                    ->modalIcon(Heroicon::ShieldCheck)
                    ->slideOver()
                    ->extraModalWindowAttributes(SlideoverModalScrollFix::extraModalWindowAttributes('farmadoc-audit-slideover-window'))
                    ->modalWidth(Width::FourExtraLarge)
                    ->modalHeading(fn (AuditLog $record): string => filled($record->uid)
                        ? 'Traza '.(string) $record->uid
                        : 'Detalle de auditoría')
                    ->modalDescription(fn (AuditLog $record): string => AuditLogEventPresentation::label((string) $record->event)
                        .' · '.($record->created_at !== null
                            ? $record->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s')
                            : '—'))
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->modalSubmitActionLabel('Listo')
                    ->modalCancelAction(false)
                    ->schema([
                        TextEntry::make('uid')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->html()
                            ->extraAttributes([
                                'class' => 'farmadoc-sale-slideover-entry',
                            ])
                            ->formatStateUsing(fn (AuditLog $record): string => AuditLogIosDetailHtml::build($record)->toHtml()),
                    ])
                    ->action(static fn () => null),
            ])
            ->toolbarActions([])
            ->poll('30s');
    }
}
