<?php

namespace App\Filament\Resources\OrderServices\Schemas;

use App\Support\Filament\BranchAuthScope;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class OrderServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la orden')
                    ->description('Identificación de la orden, aliado comercial y estado operativo.')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextInput::make('service_order_number')
                                    ->label('Número de orden')
                                    ->disabled()
                                    ->placeholder('Se asigna al guardar')
                                    ->helperText('Se genera automáticamente al crear el registro: ORD-00 + id (ej. ORD-001, ORD-0012). No se puede editar.')
                                    ->prefixIcon(Heroicon::Hashtag)
                                    ->dehydrated(false),
                                Select::make('partner_company_id')
                                    ->label('Compañía aliada')
                                    ->relationship('partnerCompany', 'legal_name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->prefixIcon(Heroicon::BuildingOffice2),
                                Select::make('status')
                                    ->label('Estado')
                                    ->required()
                                    ->native(false)
                                    ->options([
                                        'borrador' => 'Borrador',
                                        'aprobada' => 'Aprobada',
                                        'en-proceso' => 'En proceso',
                                        'finalizada' => 'Finalizada',
                                        'cancelada' => 'Cancelada',
                                    ])
                                    ->prefixIcon(Heroicon::Flag),
                                Select::make('priority')
                                    ->label('Prioridad')
                                    ->required()
                                    ->native(false)
                                    ->default('media')
                                    ->options([
                                        'baja' => 'Baja',
                                        'media' => 'Media',
                                        'alta' => 'Alta',
                                        'urgente' => 'Urgente',
                                    ])
                                    ->prefixIcon(Heroicon::Bolt),
                                TextInput::make('service_type')
                                    ->label('Tipo de servicio')
                                    ->placeholder('Consulta, procedimiento, visita…')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Squares2x2),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('client_id')
                                    ->label('Cliente')
                                    ->relationship('client', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->prefixIcon(Heroicon::User),
                                Select::make('branch_id')
                                    ->label('Sucursal')
                                    ->relationship(
                                        'branch',
                                        'name',
                                        modifyQueryUsing: function (Builder $query): Builder {
                                            $query->where('is_active', true)->orderBy('name');

                                            return BranchAuthScope::applyToBranchFormSelect($query);
                                        },
                                    )
                                    ->default(fn (): ?int => BranchAuthScope::suggestedBranchIdForOperationalForm())
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->prefixIcon(Heroicon::MapPin),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('authorization_reference')
                                    ->label('Referencia de autorización')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::ShieldCheck),
                                TextInput::make('external_reference')
                                    ->label('Referencia externa')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Link),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Paciente / beneficiario')
                    ->description('Datos del paciente cuando la orden aplica a una persona.')
                    ->icon(Heroicon::UserCircle)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 4,
                        ])
                            ->schema([
                                TextInput::make('patient_name')
                                    ->label('Nombre')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::User),
                                TextInput::make('patient_document')
                                    ->label('Documento')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Identification),
                                TextInput::make('patient_phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->placeholder('Solo números')
                                    ->helperText('Solo números, sin espacios ni caracteres especiales.')
                                    ->rule('regex:/^[0-9]*$/')
                                    ->validationMessages([
                                        'regex' => 'Este campo solo puede contener números.',
                                    ])
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                        ? preg_replace('/[^0-9]/', '', $state)
                                        : null)
                                    ->maxLength(40)
                                    ->prefixIcon(Heroicon::Phone),
                                TextInput::make('patient_email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Envelope),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Fechas')
                    ->description('Seguimiento temporal de la orden.')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 4,
                        ])
                            ->schema([
                                DateTimePicker::make('ordered_at')
                                    ->label('Emitida')
                                    ->native(false)
                                    ->prefixIcon(Heroicon::Calendar),
                                DateTimePicker::make('scheduled_at')
                                    ->label('Programada')
                                    ->native(false)
                                    ->prefixIcon(Heroicon::Clock),
                                DateTimePicker::make('started_at')
                                    ->label('Inicio')
                                    ->native(false)
                                    ->prefixIcon(Heroicon::Play),
                                DateTimePicker::make('completed_at')
                                    ->label('Cierre')
                                    ->native(false)
                                    ->prefixIcon(Heroicon::CheckCircle),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Medicamentos asociados')
                    ->description('Listado de medicamentos vinculados a esta orden (validación rápida en la ficha).')
                    ->icon(Heroicon::Beaker)
                    ->schema([
                        Repeater::make('items')
                            ->label('Ítems')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre del medicamento')
                                    ->required()
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                                Textarea::make('indicacion')
                                    ->label('Indicación')
                                    ->placeholder('Posología, duración, vía, observaciones…')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Agregar medicamento')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null)
                                ? (string) $state['name']
                                : 'Nuevo medicamento')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Montos')
                    ->description('Totales declarados para la orden.')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 4,
                        ])
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->required()
                                    ->numeric()
                                    ->default(0.0)
                                    ->prefix('$'),
                                TextInput::make('tax_total')
                                    ->label('Impuestos')
                                    ->required()
                                    ->numeric()
                                    ->default(0.0)
                                    ->prefix('$'),
                                TextInput::make('discount_total')
                                    ->label('Descuentos')
                                    ->required()
                                    ->numeric()
                                    ->default(0.0)
                                    ->prefix('$'),
                                TextInput::make('total')
                                    ->label('Total')
                                    ->required()
                                    ->numeric()
                                    ->default(0.0)
                                    ->prefix('$'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Detalle clínico e interno')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        Textarea::make('diagnosis')
                            ->label('Diagnóstico o motivo')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Notas internas')
                            ->rows(3)
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
                        ])
                            ->schema([
                                TextInput::make('created_by')
                                    ->label('Creado por')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::User),
                                TextInput::make('updated_by')
                                    ->label('Actualizado por')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::PencilSquare),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
