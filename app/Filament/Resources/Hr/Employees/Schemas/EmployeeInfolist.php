<?php

namespace App\Filament\Resources\Hr\Employees\Schemas;

use App\Enums\HrLoanFrequency;
use App\Enums\HrLoanStatus;
use App\Enums\HrRecurrence;
use App\Models\Employee;
use App\Models\HrAssignment;
use App\Models\HrDeduction;
use App\Models\HrLoan;
use App\Services\Hr\HrBcvRateResolver;
use App\Services\Hr\HrUsdVesConverter;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.infolists.components.hr-employee-profile-hero')
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'fi-hr-employee-hero-section'])
                    ->viewData(fn (Employee $record): array => [
                        'data' => self::heroData($record),
                    ]),

                Section::make('Identidad')
                    ->description('Datos personales del empleado.')
                    ->icon(Heroicon::Identification)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])->schema([
                            TextEntry::make('first_name')
                                ->label('Nombre')
                                ->icon(Heroicon::User)
                                ->weight(FontWeight::Medium)
                                ->size(TextSize::Large),
                            TextEntry::make('last_name')
                                ->label('Apellido')
                                ->icon(Heroicon::User)
                                ->weight(FontWeight::Medium)
                                ->size(TextSize::Large),
                            TextEntry::make('national_id')
                                ->label('Cédula de identidad')
                                ->icon(Heroicon::Hashtag)
                                ->badge()
                                ->color('gray')
                                ->copyable()
                                ->copyMessage('Cédula copiada'),
                            TextEntry::make('branch.name')
                                ->label('Sucursal')
                                ->icon(Heroicon::BuildingStorefront)
                                ->badge()
                                ->color('info'),
                            IconEntry::make('is_active')
                                ->label('Estado laboral')
                                ->boolean()
                                ->trueIcon(Heroicon::CheckCircle)
                                ->falseIcon(Heroicon::XCircle)
                                ->trueColor('success')
                                ->falseColor('danger'),
                            TextEntry::make('address')
                                ->label('Dirección')
                                ->icon(Heroicon::MapPin)
                                ->placeholder('Sin dirección registrada')
                                ->columnSpanFull(),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Contacto')
                    ->description('Medios para notificaciones y coordinación.')
                    ->icon(Heroicon::Phone)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            TextEntry::make('phone')
                                ->label('Teléfono')
                                ->icon(Heroicon::DevicePhoneMobile)
                                ->placeholder('—')
                                ->copyable()
                                ->copyMessage('Teléfono copiado'),
                            TextEntry::make('email')
                                ->label('Correo electrónico')
                                ->icon(Heroicon::Envelope)
                                ->placeholder('—')
                                ->copyable()
                                ->copyMessage('Correo copiado'),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Compensación')
                    ->description('Sueldo mensual y su equivalente quincenal según tasa BCV del día.')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            TextEntry::make('monthly_salary_usd')
                                ->label('Sueldo mensual (USD)')
                                ->icon(Heroicon::CurrencyDollar)
                                ->weight(FontWeight::SemiBold)
                                ->size(TextSize::Large)
                                ->formatStateUsing(fn ($state): string => 'US$ '.number_format((float) $state, 2, ',', '.')),
                            TextEntry::make('monthly_salary_ves')
                                ->label('Equivalente mensual (VES)')
                                ->icon(Heroicon::Banknotes)
                                ->state(fn (Employee $record): string => self::vesLabel((float) $record->monthly_salary_usd)),
                            TextEntry::make('biweekly_salary_usd')
                                ->label('Pago quincenal (USD)')
                                ->icon(Heroicon::CalendarDays)
                                ->state(fn (Employee $record): string => 'US$ '.number_format(round((float) $record->monthly_salary_usd / 2, 2), 2, ',', '.')),
                            TextEntry::make('biweekly_salary_ves')
                                ->label('Pago quincenal (VES)')
                                ->icon(Heroicon::CalendarDays)
                                ->state(fn (Employee $record): string => self::vesLabel(round((float) $record->monthly_salary_usd / 2, 2))),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Asignaciones')
                    ->description('Bonos y conceptos que suman al sueldo.')
                    ->icon(Heroicon::PlusCircle)
                    ->schema([
                        RepeatableEntry::make('assignments')
                            ->label('')
                            ->placeholder('Sin asignaciones registradas.')
                            ->table([
                                TableColumn::make('Concepto'),
                                TableColumn::make('Monto'),
                                TableColumn::make('Recurrencia'),
                                TableColumn::make('Vigencia'),
                                TableColumn::make('Activo')->alignment(Alignment::Center)->width('5rem'),
                            ])
                            ->schema([
                                TextEntry::make('concept')
                                    ->weight(FontWeight::Medium)
                                    ->wrap(),
                                TextEntry::make('amount_usd')
                                    ->formatStateUsing(fn ($state): string => 'US$ '.number_format((float) $state, 2, ',', '.')),
                                TextEntry::make('recurrence')
                                    ->badge()
                                    ->formatStateUsing(fn (HrRecurrence|string|null $state): string => self::recurrenceLabel($state))
                                    ->color(fn (HrRecurrence|string|null $state): string => self::recurrenceColor($state)),
                                TextEntry::make('applies_on')
                                    ->state(fn (HrAssignment $record): string => self::recurrenceWindow($record)),
                                IconEntry::make('is_active')
                                    ->boolean()
                                    ->alignCenter(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Deducciones')
                    ->description('Conceptos que se restan del sueldo.')
                    ->icon(Heroicon::MinusCircle)
                    ->schema([
                        RepeatableEntry::make('deductions')
                            ->label('')
                            ->placeholder('Sin deducciones registradas.')
                            ->table([
                                TableColumn::make('Concepto'),
                                TableColumn::make('Monto'),
                                TableColumn::make('Recurrencia'),
                                TableColumn::make('Vigencia'),
                                TableColumn::make('Activo')->alignment(Alignment::Center)->width('5rem'),
                            ])
                            ->schema([
                                TextEntry::make('concept')
                                    ->weight(FontWeight::Medium)
                                    ->wrap(),
                                TextEntry::make('amount_usd')
                                    ->formatStateUsing(fn ($state): string => 'US$ '.number_format((float) $state, 2, ',', '.')),
                                TextEntry::make('recurrence')
                                    ->badge()
                                    ->formatStateUsing(fn (HrRecurrence|string|null $state): string => self::recurrenceLabel($state))
                                    ->color(fn (HrRecurrence|string|null $state): string => self::recurrenceColor($state)),
                                TextEntry::make('applies_on')
                                    ->state(fn (HrDeduction $record): string => self::recurrenceWindow($record)),
                                IconEntry::make('is_active')
                                    ->boolean()
                                    ->alignCenter(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Préstamos')
                    ->description('Historial y saldos pendientes de este empleado.')
                    ->icon(Heroicon::CreditCard)
                    ->schema([
                        RepeatableEntry::make('loans')
                            ->label('')
                            ->placeholder('Sin préstamos registrados.')
                            ->table([
                                TableColumn::make('Concepto'),
                                TableColumn::make('Monto'),
                                TableColumn::make('Saldo'),
                                TableColumn::make('Frecuencia'),
                                TableColumn::make('Estatus'),
                            ])
                            ->schema([
                                TextEntry::make('concept')
                                    ->placeholder('Sin concepto')
                                    ->weight(FontWeight::Medium)
                                    ->wrap(),
                                TextEntry::make('amount_usd')
                                    ->formatStateUsing(fn ($state): string => 'US$ '.number_format((float) $state, 2, ',', '.')),
                                TextEntry::make('remaining_usd')
                                    ->formatStateUsing(fn ($state): string => 'US$ '.number_format((float) $state, 2, ',', '.')),
                                TextEntry::make('frequency')
                                    ->formatStateUsing(fn (HrLoanFrequency|string|null $state): string => $state instanceof HrLoanFrequency
                                        ? $state->label()
                                        : (HrLoanFrequency::tryFrom((string) $state)?->label() ?? '—')),
                                TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing(fn (HrLoanStatus|string|null $state): string => $state instanceof HrLoanStatus
                                        ? $state->label()
                                        : (HrLoanStatus::tryFrom((string) $state)?->label() ?? '—'))
                                    ->color(fn (HrLoanStatus|string|null $state): string => $state instanceof HrLoanStatus
                                        ? $state->filamentColor()
                                        : (HrLoanStatus::tryFrom((string) $state)?->filamentColor() ?? 'gray')),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Fechas del registro.')
                    ->icon(Heroicon::Clock)
                    ->collapsed()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            TextEntry::make('created_at')
                                ->label('Creado')
                                ->dateTime('d/m/Y H:i')
                                ->icon(Heroicon::Calendar),
                            TextEntry::make('updated_at')
                                ->label('Última actualización')
                                ->dateTime('d/m/Y H:i')
                                ->icon(Heroicon::ArrowPath),
                        ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function heroData(Employee $record): array
    {
        $record->loadMissing(['branch', 'assignments', 'deductions', 'loans']);

        $monthly = (float) $record->monthly_salary_usd;
        $biweekly = round($monthly / 2, 2);
        $rate = app(HrBcvRateResolver::class)->resolveForDate(now());

        $activeLoans = $record->loans->filter(
            fn (HrLoan $loan): bool => $loan->status === HrLoanStatus::Active && (float) $loan->remaining_usd > 0,
        );
        $loanRemaining = round((float) $activeLoans->sum('remaining_usd'), 2);

        $initials = mb_strtoupper(
            mb_substr((string) $record->first_name, 0, 1).mb_substr((string) $record->last_name, 0, 1),
        );

        return [
            'initials' => $initials !== '' ? $initials : 'EM',
            'full_name' => $record->fullName(),
            'national_id' => $record->national_id,
            'branch' => $record->branch?->name ?? 'Sin sucursal',
            'is_active' => (bool) $record->is_active,
            'phone' => $record->phone,
            'email' => $record->email,
            'monthly_usd' => 'US$ '.number_format($monthly, 2, ',', '.'),
            'monthly_ves' => $rate ? 'Bs '.number_format(HrUsdVesConverter::toVes($monthly, $rate), 2, ',', '.') : null,
            'biweekly_usd' => 'US$ '.number_format($biweekly, 2, ',', '.'),
            'biweekly_ves' => $rate ? 'Bs '.number_format(HrUsdVesConverter::toVes($biweekly, $rate), 2, ',', '.') : null,
            'rate_label' => $rate ? 'Tasa BCV '.number_format($rate, 4, ',', '.') : 'Tasa BCV no disponible',
            'assignments_count' => $record->assignments->where('is_active', true)->count(),
            'deductions_count' => $record->deductions->where('is_active', true)->count(),
            'active_loans_count' => $activeLoans->count(),
            'loan_remaining_usd' => 'US$ '.number_format($loanRemaining, 2, ',', '.'),
        ];
    }

    private static function vesLabel(float $usd): string
    {
        $rate = app(HrBcvRateResolver::class)->resolveForDate(now());
        if ($rate === null) {
            return 'Tasa BCV no disponible';
        }

        return 'Bs '.number_format(HrUsdVesConverter::toVes($usd, $rate), 2, ',', '.')
            .' · tasa '.number_format($rate, 4, ',', '.');
    }

    private static function recurrenceLabel(HrRecurrence|string|null $state): string
    {
        if ($state instanceof HrRecurrence) {
            return $state->label();
        }

        return HrRecurrence::tryFrom((string) $state)?->label() ?? '—';
    }

    private static function recurrenceColor(HrRecurrence|string|null $state): string
    {
        $value = $state instanceof HrRecurrence ? $state : HrRecurrence::tryFrom((string) $state);

        return match ($value) {
            HrRecurrence::Once => 'warning',
            HrRecurrence::Recurring => 'success',
            default => 'gray',
        };
    }

    private static function recurrenceWindow(HrAssignment|HrDeduction $record): string
    {
        if ($record->recurrence === HrRecurrence::Once) {
            return $record->applies_on?->format('d/m/Y') ?? '—';
        }

        $from = $record->starts_on?->format('d/m/Y') ?? 'inicio';
        $to = $record->ends_on?->format('d/m/Y') ?? 'sin fin';

        return "{$from} → {$to}";
    }
}
