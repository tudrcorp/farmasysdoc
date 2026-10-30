<?php

namespace App\Filament\Resources\Hr\Recruitment\Candidates\Schemas;

use App\Enums\RecruitmentCandidateSource;
use App\Models\RecruitmentCandidate;
use App\Support\Recruitment\RecruitmentNationalId;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class RecruitmentCandidateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make('Revisa los datos antes de guardar')
                    ->description('La IA leyó este CV. Compara con el documento, sobre todo los campos marcados con «Verificar».')
                    ->icon(Heroicon::Sparkles)
                    ->color('warning')
                    ->visible(fn (?RecruitmentCandidate $record): bool => $record !== null && ! $record->isReviewed() && $record->cv_extraction_error === null)
                    ->columnSpanFull(),
                Callout::make('No se pudo leer el CV')
                    ->description(fn (?RecruitmentCandidate $record): string => (string) $record?->cv_extraction_error)
                    ->icon(Heroicon::ExclamationTriangle)
                    ->color('danger')
                    ->visible(fn (?RecruitmentCandidate $record): bool => filled($record?->cv_extraction_error))
                    ->columnSpanFull(),
                Grid::make(['default' => 1, 'xl' => 2])
                    ->schema([
                        Group::make(self::dataSections()),
                        Section::make('Curriculum vitae')
                            ->icon(Heroicon::DocumentText)
                            ->extraAttributes(['class' => 'xl:sticky xl:top-20'])
                            ->schema([
                                View::make('filament.recruitment.candidate-cv-preview')
                                    ->visible(fn (?RecruitmentCandidate $record): bool => $record?->hasCv() ?? false),
                                self::cvUpload()
                                    ->visible(fn (?RecruitmentCandidate $record): bool => ! ($record?->hasCv() ?? false)),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function cvUpload(): FileUpload
    {
        return FileUpload::make('cv_path')
            ->label('Curriculum vitae')
            ->helperText('PDF, Word (.docx) o imagen. Máximo 10 MB.')
            ->disk(RecruitmentCandidate::CV_DISK)
            ->directory('recruitment/cvs')
            ->visibility('private')
            ->acceptedFileTypes(config('recruitment.cv_accepted_mime_types'))
            ->maxSize(config('recruitment.cv_max_size_kb'))
            ->storeFileNamesIn('cv_original_name');
    }

    /**
     * @return list<Section>
     */
    private static function dataSections(): array
    {
        return [
            Section::make('Datos personales')
                ->icon(Heroicon::User)
                ->schema([
                    FileUpload::make('photo_path')
                        ->label('Foto')
                        ->helperText(fn (?RecruitmentCandidate $record): string => filled($record?->cv_photo_error)
                            ? "No se pudo sacar la foto del CV: {$record->cv_photo_error} Súbela a mano."
                            : 'Confirma que sea la foto del candidato o cámbiala.')
                        ->image()
                        ->avatar()
                        ->imageEditor()
                        ->disk(RecruitmentCandidate::PHOTO_DISK)
                        ->directory('recruitment/photos')
                        ->visibility('public')
                        ->maxSize(2048)
                        ->hint(self::flagHint('photo_path'))
                        ->hintColor('warning'),
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        self::verifiable(TextInput::make('first_name')->label('Nombre')->required()->maxLength(255)),
                        self::verifiable(TextInput::make('last_name')->label('Apellido')->required()->maxLength(255)),
                        self::nationalIdInput(),
                        DatePicker::make('birth_date')
                            ->label('Fecha de nacimiento')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now()),
                        self::verifiable(TextInput::make('phone')->label('Teléfono')->tel()->maxLength(40)),
                        self::verifiable(TextInput::make('email')->label('Email')->email()->maxLength(255)),
                    ]),
                    self::verifiable(Textarea::make('address')->label('Dirección de habitación')->rows(2)),
                ]),
            Section::make('Perfil')
                ->icon(Heroicon::AcademicCap)
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextInput::make('desired_position')->label('Cargo al que aspira')->maxLength(160),
                        TextInput::make('years_of_experience')
                            ->label('Años de experiencia')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(60),
                        Select::make('preferred_branch_id')
                            ->label('Sucursal preferida')
                            ->relationship('preferredBranch', 'name', fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('name'))
                            ->searchable()
                            ->preload(),
                        Select::make('source')
                            ->label('Cómo llegó a la empresa')
                            ->options(RecruitmentCandidateSource::options())
                            ->native(false),
                    ]),
                    Textarea::make('education')->label('Estudios')->rows(3),
                    Textarea::make('work_experience')->label('Experiencia laboral')->rows(4),
                ]),
        ];
    }

    private static function nationalIdInput(): TextInput
    {
        return self::verifiable(
            TextInput::make('national_id')
                ->label('Cédula de identidad')
                ->required()
                ->maxLength(50)
                ->live(onBlur: true)
                ->helperText(fn (Get $get): ?string => self::employeeNotice($get('national_id')))
                ->dehydrateStateUsing(fn (?string $state): ?string => RecruitmentNationalId::normalize($state) ?? $state)
                ->rule(fn (?RecruitmentCandidate $record): Closure => self::nationalIdRule($record)),
        );
    }

    private static function nationalIdRule(?RecruitmentCandidate $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            if (RecruitmentNationalId::normalize((string) $value) === null) {
                $fail('Escribe una cédula válida, por ejemplo V-12345678.');

                return;
            }

            $duplicate = RecruitmentNationalId::findCandidate((string) $value, $record?->id);
            if ($duplicate !== null) {
                $fail("Esta cédula ya está registrada para {$duplicate->fullName()}.");
            }
        };
    }

    private static function employeeNotice(mixed $nationalId): ?string
    {
        $employee = RecruitmentNationalId::findEmployee(is_string($nationalId) ? $nationalId : null);
        if ($employee === null) {
            return null;
        }

        $state = $employee->is_active ? 'activo' : 'inactivo';

        return "Aviso: esta cédula ya pertenece a {$employee->fullName()}, empleado {$state}.";
    }

    /**
     * @template TField of Field
     *
     * @param  TField  $field
     * @return TField
     */
    private static function verifiable(Field $field): Field
    {
        return $field
            ->hint(self::flagHint($field->getName()))
            ->hintColor('warning')
            ->hintIcon(fn (?RecruitmentCandidate $record): ?Heroicon => self::isFlagged($record, $field->getName()) ? Heroicon::ExclamationTriangle : null);
    }

    private static function flagHint(string $field): Closure
    {
        return fn (?RecruitmentCandidate $record): ?string => self::isFlagged($record, $field) ? 'Verificar' : null;
    }

    private static function isFlagged(?RecruitmentCandidate $record, string $field): bool
    {
        return $record !== null && ! $record->isReviewed() && $record->isFieldFlagged($field);
    }
}
