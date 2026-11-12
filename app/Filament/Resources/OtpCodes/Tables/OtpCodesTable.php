<?php

namespace App\Filament\Resources\OtpCodes\Tables;

use App\Enums\OtpCodePurpose;
use App\Enums\OtpCodeStatus;
use App\Models\OtpCode;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OtpCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->emptyStateHeading('Sin claves OTP')
            ->emptyStateDescription('Cuando un usuario solicite una clave para autorizar una operación, aparecerá aquí.')
            ->emptyStateIcon(Heroicon::Key)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Solicitada')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Clave')
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large)
                    ->copyable(fn (OtpCode $record): bool => $record->effective_status === OtpCodeStatus::Pending)
                    ->copyMessage('Clave copiada')
                    ->color(fn (OtpCode $record): string => $record->effective_status === OtpCodeStatus::Pending ? 'success' : 'gray')
                    ->formatStateUsing(fn (OtpCode $record): string => $record->effective_status === OtpCodeStatus::Pending
                        ? (string) $record->code
                        : '••••••'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (OtpCode $record): OtpCodeStatus => $record->effective_status)
                    ->formatStateUsing(fn (OtpCodeStatus $state): string => $state->label())
                    ->color(fn (OtpCodeStatus $state): string => $state->filamentColor())
                    ->description(fn (OtpCode $record): ?string => self::statusDescription($record)),
                TextColumn::make('purpose')
                    ->label('Módulo')
                    ->badge()
                    ->formatStateUsing(fn (OtpCodePurpose $state): string => $state->label())
                    ->color(fn (OtpCodePurpose $state): string => $state->filamentColor()),
                TextColumn::make('requestedBy.name')
                    ->label('Solicitada por')
                    ->description(fn (OtpCode $record): ?string => $record->requestedBy?->email)
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('summary')
                    ->label('Detalle')
                    ->limit(60)
                    ->tooltip(fn (OtpCode $record): ?string => $record->summary)
                    ->wrap()
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('usedBy.name')
                    ->label('Usada por')
                    ->description(fn (OtpCode $record): ?string => $record->used_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s'))
                    ->placeholder('—'),
                TextColumn::make('self_applied')
                    ->label('Autorización')
                    ->badge()
                    ->state(fn (OtpCode $record): ?string => match (true) {
                        $record->self_applied => 'Usar OTP (automática)',
                        $record->used_at !== null => 'Tercero',
                        default => null,
                    })
                    ->color(fn (OtpCode $record): string => $record->self_applied ? 'warning' : 'gray')
                    ->icon(fn (OtpCode $record): ?Heroicon => $record->self_applied ? Heroicon::ExclamationTriangle : null)
                    ->tooltip(fn (OtpCode $record): ?string => $record->self_applied
                        ? 'La usó quien la pidió marcando «Usar OTP», sin que un gerente o administrador se la indicara.'
                        : null)
                    ->placeholder('—'),
                TextColumn::make('used_in')
                    ->label('Usada en')
                    ->limit(50)
                    ->tooltip(fn (OtpCode $record): ?string => $record->used_in)
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(OtpCodeStatus::options())
                    ->query(function (Builder $query, array $data): Builder {
                        $status = OtpCodeStatus::tryFrom((string) ($data['value'] ?? ''));

                        return $status instanceof OtpCodeStatus
                            ? $query->withEffectiveStatus($status)
                            : $query;
                    }),
                SelectFilter::make('self_applied')
                    ->label('Autorización')
                    ->options([
                        '1' => 'Usar OTP (automática)',
                        '0' => 'Indicada por un tercero',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ((string) ($data['value'] ?? '')) {
                        '1' => $query->where('self_applied', true),
                        '0' => $query->where('self_applied', false)->whereNotNull('used_at'),
                        default => $query,
                    }),
                SelectFilter::make('purpose')
                    ->label('Módulo')
                    ->options(OtpCodePurpose::options()),
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('viewOtpDetail')
                    ->label('Ver')
                    ->icon(Heroicon::Eye)
                    ->modalIcon(Heroicon::Key)
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalHeading(fn (OtpCode $record): string => 'Clave OTP · '.$record->purpose->label())
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('effective_status')
                                    ->label('Estado')
                                    ->badge()
                                    ->state(fn (OtpCode $record): OtpCodeStatus => $record->effective_status)
                                    ->formatStateUsing(fn (OtpCodeStatus $state): string => $state->label())
                                    ->color(fn (OtpCodeStatus $state): string => $state->filamentColor()),
                                TextEntry::make('expires_at')
                                    ->label('Vence')
                                    ->dateTime('d/m/Y H:i:s'),
                                TextEntry::make('requestedBy.name')
                                    ->label('Solicitada por')
                                    ->placeholder('—'),
                                TextEntry::make('branch.name')
                                    ->label('Sucursal')
                                    ->placeholder('—'),
                                TextEntry::make('summary')
                                    ->label('Resumen')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('details')
                                    ->label('Detalle de la operación')
                                    ->listWithLineBreaks()
                                    ->bulleted()
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('usedBy.name')
                                    ->label('Usada por')
                                    ->placeholder('—'),
                                TextEntry::make('used_at')
                                    ->label('Usada el')
                                    ->dateTime('d/m/Y H:i:s')
                                    ->placeholder('—'),
                                TextEntry::make('self_applied_label')
                                    ->label('Autorización')
                                    ->badge()
                                    ->state(fn (OtpCode $record): string => $record->self_applied
                                        ? 'Usar OTP (automática) · '.$record->self_applied_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s')
                                        : ($record->used_at !== null ? 'Indicada por un tercero' : 'Sin usar'))
                                    ->color(fn (OtpCode $record): string => $record->self_applied ? 'warning' : 'gray'),
                                TextEntry::make('self_applied_ip')
                                    ->label('IP del uso automático')
                                    ->placeholder('—')
                                    ->visible(fn (OtpCode $record): bool => $record->self_applied),
                                TextEntry::make('used_in')
                                    ->label('Lugar del sistema')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('used_url')
                                    ->label('Pantalla')
                                    ->url(fn (OtpCode $record): ?string => $record->used_url)
                                    ->openUrlInNewTab()
                                    ->limit(80)
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('cancellation_reason')
                                    ->label('Motivo de anulación')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('failed_attempts')
                                    ->label('Intentos fallidos'),
                            ]),
                    ]),
            ])
            ->toolbarActions([])
            ->poll('5s');
    }

    private static function statusDescription(OtpCode $record): ?string
    {
        return match ($record->effective_status) {
            OtpCodeStatus::Pending => 'Vence '.$record->expires_at?->diffForHumans(),
            OtpCodeStatus::Cancelled => $record->cancellation_reason,
            default => null,
        };
    }
}
