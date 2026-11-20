<?php

namespace App\Filament\BusinessPartners\Resources\OrderServices\Schemas;

use App\Filament\BusinessPartners\Resources\Orders\OrderResource;
use App\Models\OrderService;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

class OrderServiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Orden de servicio')
                    ->icon(Heroicon::ClipboardDocumentCheck)
                    ->schema([
                        TextEntry::make('service_order_number')
                            ->label('Número')
                            ->badge()
                            ->color('primary')
                            ->copyable(),
                        TextEntry::make('order.order_number')
                            ->label('Pedido asociado')
                            ->badge()
                            ->color('info')
                            ->copyable()
                            ->placeholder('—')
                            ->url(fn (OrderService $record): ?string => $record->order_id
                                ? OrderResource::getUrl('view', ['record' => $record->order_id], isAbsolute: false)
                                : null),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match (strtolower((string) $state)) {
                                'en-proceso', 'en proceso' => 'En proceso',
                                'finalizada' => 'Finalizada',
                                'cancelada' => 'Cancelada',
                                'aprobada' => 'Aprobada',
                                'borrador' => 'Borrador',
                                default => filled($state) ? (string) $state : '—',
                            }),
                        TextEntry::make('external_reference')
                            ->label('Referencia')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('branch.name')
                            ->label('Sucursal')
                            ->placeholder('—'),
                        TextEntry::make('ordered_at')
                            ->label('Emitida')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Paciente')
                    ->icon(Heroicon::UserCircle)
                    ->schema([
                        TextEntry::make('patient_name')->label('Nombre')->placeholder('—'),
                        TextEntry::make('patient_document')->label('Documento')->placeholder('—')->copyable(),
                        TextEntry::make('patient_phone')->label('Teléfono')->placeholder('—')->copyable(),
                        TextEntry::make('patient_email')->label('Correo')->placeholder('—')->copyable(),
                        TextEntry::make('diagnosis')
                            ->label('Diagnóstico o motivo')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Medicamentos')
                    ->icon(Heroicon::Beaker)
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->placeholder('No hay medicamentos en esta orden.')
                            ->state(fn (OrderService $record): array => $record->medicationItemsForDisplay())
                            ->table([
                                TableColumn::make('N.º')->width('4rem')->alignment(Alignment::Center),
                                TableColumn::make('Medicamento'),
                                TableColumn::make('Código de barra'),
                                TableColumn::make('Indicación'),
                            ])
                            ->schema([
                                TextEntry::make('position')->alignCenter()->badge()->color('gray'),
                                TextEntry::make('name')->placeholder('—'),
                                TextEntry::make('barcode')->placeholder('—')->copyable()->fontFamily('mono'),
                                TextEntry::make('indicacion')->placeholder('—')->wrap(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
