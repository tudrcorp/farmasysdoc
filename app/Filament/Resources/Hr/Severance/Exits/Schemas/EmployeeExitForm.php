<?php

namespace App\Filament\Resources\Hr\Severance\Exits\Schemas;

use App\Enums\EmployeeExitReason;
use App\Enums\EmployeeExitStatus;
use App\Models\Employee;
use App\Models\EmployeeExit;
use App\Support\Filament\BranchAuthScope;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EmployeeExitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Egreso')
                    ->icon(Heroicon::ArrowRightStartOnRectangle)
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('employee_id')
                                ->label('Empleado')
                                ->options(fn (): array => self::eligibleEmployees())
                                ->searchable()
                                ->required(),
                            Select::make('reason')
                                ->label('Motivo')
                                ->options(EmployeeExitReason::options())
                                ->native(false)
                                ->required(),
                            DatePicker::make('last_work_day')
                                ->label('Último día de trabajo')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->required(),
                            FileUpload::make('document_path')
                                ->label('Soporte')
                                ->helperText('Carta de renuncia, despido u otro soporte. PDF o imagen, máximo 10 MB.')
                                ->disk(EmployeeExit::DOCUMENT_DISK)
                                ->directory('hr/exits')
                                ->visibility('private')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(10240)
                                ->storeFileNamesIn('document_original_name'),
                        ]),
                        Textarea::make('notes')->label('Observaciones')->rows(3)->maxLength(2000)->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function eligibleEmployees(): array
    {
        return BranchAuthScope::apply(Employee::query())
            ->where('is_active', true)
            ->whereDoesntHave('exits', fn ($query) => $query->where('status', EmployeeExitStatus::Open))
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->fullName()])
            ->all();
    }
}
