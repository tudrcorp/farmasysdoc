<?php

namespace App\Filament\Resources\OrderServices\Schemas;

use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Models\OrderService;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
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
                Section::make('Resumen de la orden')
                    ->description('Identificación operativa y estado de la orden de servicio.')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('service_order_number')
                                    ->label('Número de orden')
                                    ->badge()
                                    ->color('primary')
                                    ->copyable()
                                    ->copyMessage('Número copiado')
                                    ->placeholder('—'),
                                TextEntry::make('partnerCompany.legal_name')
                                    ->label('Compañía aliada')
                                    ->weight('medium')
                                    ->icon(Heroicon::BuildingOffice2)
                                    ->iconColor('gray')
                                    ->placeholder('—')
                                    ->url(fn (OrderService $record): ?string => $record->partner_company_id
                                        ? PartnerCompanyResource::getUrl('view', ['record' => $record->partner_company_id], isAbsolute: false)
                                        : null),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (?string $state): string => self::statusColor($state))
                                    ->formatStateUsing(fn (?string $state): string => self::formatStatusLabel($state))
                                    ->placeholder('—'),
                                TextEntry::make('priority')
                                    ->label('Prioridad')
                                    ->badge()
                                    ->color(fn (?string $state): string => self::priorityColor($state))
                                    ->formatStateUsing(fn (?string $state): string => self::formatPriorityLabel($state))
                                    ->placeholder('—'),
                                TextEntry::make('service_type')
                                    ->label('Tipo de servicio')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Squares2x2)
                                    ->iconColor('gray'),
                                TextEntry::make('authorization_reference')
                                    ->label('Ref. autorización')
                                    ->copyable()
                                    ->placeholder('—')
                                    ->icon(Heroicon::ShieldCheck)
                                    ->iconColor('gray'),
                                TextEntry::make('external_reference')
                                    ->label('Ref. externa')
                                    ->copyable()
                                    ->placeholder('—')
                                    ->icon(Heroicon::Link)
                                    ->iconColor('gray'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Cliente y cobertura')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('client.name')
                                    ->label('Cliente')
                                    ->placeholder('—')
                                    ->icon(Heroicon::User)
                                    ->iconColor('gray'),
                                TextEntry::make('branch.name')
                                    ->label('Sucursal')
                                    ->placeholder('—')
                                    ->icon(Heroicon::MapPin)
                                    ->iconColor('gray'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Paciente / beneficiario')
                    ->icon(Heroicon::UserCircle)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('patient_name')
                                    ->label('Nombre')
                                    ->placeholder('—'),
                                TextEntry::make('patient_document')
                                    ->label('Documento')
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('patient_phone')
                                    ->label('Teléfono')
                                    ->copyable()
                                    ->placeholder('—')
                                    ->icon(Heroicon::Phone)
                                    ->iconColor('gray'),
                                TextEntry::make('patient_email')
                                    ->label('Correo electrónico')
                                    ->copyable()
                                    ->placeholder('—')
                                    ->icon(Heroicon::Envelope)
                                    ->iconColor('gray'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Medicamentos de la orden')
                    ->description('Revise los medicamentos declarados para esta orden.')
                    ->icon(Heroicon::Beaker)
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('Medicamentos asociados')
                            ->placeholder('No hay medicamentos registrados en esta orden.')
                            ->table([
                                TableColumn::make('N.º')
                                    ->width('4rem')
                                    ->alignment(Alignment::Center),
                                TableColumn::make('Medicamento'),
                                TableColumn::make('Indicación'),
                            ])
                            ->schema([
                                TextEntry::make('position')
                                    ->alignCenter()
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('name')
                                    ->weight('medium')
                                    ->placeholder('—'),
                                TextEntry::make('indicacion')
                                    ->label('')
                                    ->placeholder('—')
                                    ->prose()
                                    ->wrap(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Fechas')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 4,
                        ])
                            ->schema([
                                TextEntry::make('ordered_at')
                                    ->label('Emitida')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('scheduled_at')
                                    ->label('Programada')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('started_at')
                                    ->label('Inicio')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('completed_at')
                                    ->label('Cierre')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Montos')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 4,
                        ])
                            ->schema([
                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money('COP')
                                    ->placeholder('—'),
                                TextEntry::make('tax_total')
                                    ->label('Impuestos')
                                    ->money('COP')
                                    ->placeholder('—'),
                                TextEntry::make('discount_total')
                                    ->label('Descuentos')
                                    ->money('COP')
                                    ->placeholder('—'),
                                TextEntry::make('total')
                                    ->label('Total')
                                    ->money('COP')
                                    ->weight('bold')
                                    ->color('primary')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Detalle y notas')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        TextEntry::make('diagnosis')
                            ->label('Diagnóstico o motivo')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->prose(),
                        TextEntry::make('notes')
                            ->label('Notas internas')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->icon(Heroicon::FingerPrint)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 4,
                        ])
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('—'),
                                TextEntry::make('updated_by')
                                    ->label('Actualizado por')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Fecha creación')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function statusColor(?string $status): string
    {
        $key = strtolower((string) $status);

        return match ($key) {
            'borrador' => 'gray',
            'aprobada' => 'info',
            'en-proceso', 'en proceso' => 'warning',
            'finalizada' => 'success',
            'cancelada' => 'danger',
            default => 'gray',
        };
    }

    private static function formatStatusLabel(?string $status): string
    {
        if (blank($status)) {
            return '—';
        }

        return match (strtolower($status)) {
            'borrador' => 'Borrador',
            'aprobada' => 'Aprobada',
            'en-proceso', 'en proceso' => 'En proceso',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
            default => (string) $status,
        };
    }

    private static function priorityColor(?string $priority): string
    {
        return match (strtolower((string) $priority)) {
            'baja' => 'gray',
            'media' => 'info',
            'alta' => 'warning',
            'urgente' => 'danger',
            default => 'gray',
        };
    }

    private static function formatPriorityLabel(?string $priority): string
    {
        if (blank($priority)) {
            return '—';
        }

        return match (strtolower($priority)) {
            'baja' => 'Baja',
            'media' => 'Media',
            'alta' => 'Alta',
            'urgente' => 'Urgente',
            default => (string) $priority,
        };
    }
}
