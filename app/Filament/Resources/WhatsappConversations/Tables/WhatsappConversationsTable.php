<?php

namespace App\Filament\Resources\WhatsappConversations\Tables;

use App\Enums\WhatsappConversationHandler;
use App\Enums\WhatsappConversationStatus;
use App\Filament\Resources\WhatsappConversations\WhatsappConversationResource;
use App\Models\WhatsappConversation;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class WhatsappConversationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('15s')
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [
                    WhatsappConversationStatus::NeedsAnalyst->value,
                    WhatsappConversationStatus::PendingNextShift->value,
                ])
                ->orderByDesc('last_inbound_at'))
            ->recordUrl(fn (WhatsappConversation $record): string => WhatsappConversationResource::getUrl('index', ['conversacion' => $record->getKey()]))
            ->columns([
                TextColumn::make('contact.full_name')
                    ->label('Cliente')
                    ->state(fn (WhatsappConversation $record): string => $record->contact?->displayName() ?? '—')
                    ->description(fn (WhatsappConversation $record): ?string => $record->contact?->localPhone())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('contact', fn (Builder $contact): Builder => $contact
                        ->where('full_name', 'like', '%'.$search.'%')
                        ->orWhere('profile_name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.preg_replace('/\D+/', '', $search).'%')
                        ->orWhere('document_number', 'like', '%'.$search.'%'))),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?WhatsappConversationStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?WhatsappConversationStatus $state): string => $state?->filamentColor() ?? 'gray'),
                TextColumn::make('stage')
                    ->label('Etapa')
                    ->state(fn (WhatsappConversation $record): string => $record->stageLabel())
                    ->toggleable(),
                TextColumn::make('escalation_reason')
                    ->label('Motivo')
                    ->limit(60)
                    ->tooltip(fn (WhatsappConversation $record): ?string => $record->escalation_reason)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('handled_by')
                    ->label('Atiende')
                    ->state(fn (WhatsappConversation $record): string => $record->handled_by === WhatsappConversationHandler::Analyst
                        ? ($record->assignedUser?->name ?? 'Analista')
                        : 'Asistente')
                    ->icon(fn (WhatsappConversation $record): string => $record->handled_by === WhatsappConversationHandler::Analyst ? 'heroicon-o-user' : 'heroicon-o-sparkles'),
                TextColumn::make('waiting')
                    ->label('Esperando')
                    ->state(fn (WhatsappConversation $record): ?string => ($minutes = $record->minutesWaitingForAnalyst()) !== null ? self::waitingText($minutes) : null)
                    ->color(fn (WhatsappConversation $record): string => match (true) {
                        ($record->minutesWaitingForAnalyst() ?? 0) >= 30 => 'danger',
                        ($record->minutesWaitingForAnalyst() ?? 0) >= 10 => 'warning',
                        default => 'gray',
                    })
                    ->weight('bold')
                    ->placeholder('—'),
                TextColumn::make('last_inbound_at')
                    ->label('Último mensaje')
                    ->since()
                    ->dateTimeTooltip('d/m/Y h:i a')
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(WhatsappConversationStatus::options()),
                SelectFilter::make('assigned_user_id')
                    ->label('Analista')
                    ->relationship('assignedUser', 'name'),
            ])
            ->emptyStateHeading('No hay conversaciones')
            ->emptyStateDescription('Aquí aparecen los pedidos que llegan por WhatsApp.');
    }

    private static function waitingText(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);

        return $hours < 24 ? $hours.' h '.($minutes % 60).' min' : intdiv($hours, 24).' d '.($hours % 24).' h';
    }
}
