<?php

namespace App\Filament\Resources\SupplierReturns\Schemas;

use App\Enums\SupplierReturnEventType;
use App\Enums\SupplierReturnStatus;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnAttachment;
use App\Services\Purchases\SupplierReturnNotifier;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class SupplierReturnInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Recepción')
                    ->icon(Heroicon::Truck)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('number')->label('Número')->badge()->color('primary')->copyable()->size(TextSize::Large),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->formatStateUsing(fn (SupplierReturnStatus $state): string => $state->label())
                                    ->badge()
                                    ->color(fn (SupplierReturnStatus $state): string => $state->color()),
                                TextEntry::make('branch.name')->label('Sucursal'),
                                TextEntry::make('supplier_name')
                                    ->label('Proveedor')
                                    ->state(fn (SupplierReturn $record): string => $record->supplierName())
                                    ->weight(FontWeight::SemiBold),
                                TextEntry::make('purchase.purchase_number')
                                    ->label('Compra relacionada')
                                    ->placeholder('—')
                                    ->url(fn (SupplierReturn $record): ?string => $record->purchase_id
                                        ? PurchaseResource::getUrl('view', ['record' => $record->purchase_id])
                                        : null),
                                TextEntry::make('received_on')->label('Recibida el')->date('d/m/Y'),
                                TextEntry::make('creator.name')->label('Registrada por')->placeholder('—')
                                    ->hint(fn (SupplierReturn $record): ?string => $record->created_at?->format('d/m/Y H:i')),
                                TextEntry::make('total_excess')
                                    ->label('Unidades de más')
                                    ->state(fn (SupplierReturn $record): string => SupplierReturnNotifier::quantity($record->totalExcess()))
                                    ->badge()
                                    ->color('warning'),
                                TextEntry::make('notes')->label('Nota')->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Productos')
                    ->icon(Heroicon::QrCode)
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Código de barras'),
                                TableColumn::make('Producto'),
                                TableColumn::make('Facturada'),
                                TableColumn::make('Recibida'),
                                TableColumn::make('De más'),
                            ])
                            ->schema([
                                TextEntry::make('barcode')->placeholder('—')->copyable()->fontFamily('mono'),
                                TextEntry::make('product_name')
                                    ->weight(FontWeight::Medium)
                                    ->hint(fn ($record): ?string => $record->product_id ? null : 'Fuera del catálogo'),
                                TextEntry::make('quantity_invoiced')->formatStateUsing(fn ($state): string => SupplierReturnNotifier::quantity((float) $state)),
                                TextEntry::make('quantity_received')->formatStateUsing(fn ($state): string => SupplierReturnNotifier::quantity((float) $state)),
                                TextEntry::make('quantity_excess')
                                    ->formatStateUsing(fn ($state): string => SupplierReturnNotifier::quantity((float) $state))
                                    ->badge()
                                    ->color('warning'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Revisión y entrega')
                    ->icon(Heroicon::ShieldCheck)
                    ->visible(fn (SupplierReturn $record): bool => $record->status !== SupplierReturnStatus::PendingReview)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('reviewer.name')->label('Revisada por')->placeholder('—')
                                    ->hint(fn (SupplierReturn $record): ?string => $record->reviewed_at?->format('d/m/Y H:i')),
                                TextEntry::make('review_notes')->label('Observación')->placeholder('—'),
                                TextEntry::make('rejection_reason')->label('Motivo del rechazo')->color('danger')
                                    ->visible(fn (SupplierReturn $record): bool => filled($record->rejection_reason))
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                                TextEntry::make('returned_on')->label('Entregada al proveedor')->date('d/m/Y')->placeholder('—')
                                    ->visible(fn (SupplierReturn $record): bool => $record->status === SupplierReturnStatus::Returned),
                                TextEntry::make('returner.name')->label('Entregada por')->placeholder('—')
                                    ->visible(fn (SupplierReturn $record): bool => $record->status === SupplierReturnStatus::Returned),
                                TextEntry::make('return_notes')->label('Nota de entrega')->placeholder('—')
                                    ->visible(fn (SupplierReturn $record): bool => $record->status === SupplierReturnStatus::Returned)
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Fotos y soportes')
                    ->icon(Heroicon::Camera)
                    ->schema([
                        RepeatableEntry::make('attachments')
                            ->hiddenLabel()
                            ->placeholder('Sin adjuntos.')
                            ->table([
                                TableColumn::make('Archivo'),
                                TableColumn::make('Etapa'),
                                TableColumn::make('Subido'),
                            ])
                            ->schema([
                                TextEntry::make('original_name')
                                    ->state(fn (SupplierReturnAttachment $record): string => $record->displayName())
                                    ->url(fn (SupplierReturnAttachment $record): string => $record->url())
                                    ->openUrlInNewTab()
                                    ->icon(Heroicon::PaperClip)
                                    ->color('primary'),
                                TextEntry::make('stage')->state(fn (SupplierReturnAttachment $record): string => $record->stageLabel())->badge()->color('gray'),
                                TextEntry::make('created_at')->dateTime('d/m/Y H:i'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Bitácora')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        RepeatableEntry::make('events')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Fecha'),
                                TableColumn::make('Evento'),
                                TableColumn::make('Usuario'),
                                TableColumn::make('Detalle'),
                            ])
                            ->schema([
                                TextEntry::make('created_at')->dateTime('d/m/Y H:i'),
                                TextEntry::make('type')
                                    ->formatStateUsing(fn (SupplierReturnEventType $state): string => $state->label())
                                    ->badge()
                                    ->color(fn (SupplierReturnEventType $state): string => $state->color()),
                                TextEntry::make('user.name')->placeholder('Sistema'),
                                TextEntry::make('notes')->placeholder('—'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
