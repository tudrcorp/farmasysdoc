<?php

namespace App\Filament\Pages\Hr;

use App\Filament\Resources\Hr\Concerns\ChecksHrAccess;
use App\Models\EmploymentCertificateSetting;
use App\Services\Hr\EmploymentCertificatePdfFactory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Plantilla única de la constancia de trabajo: el texto con variables, el firmante, su firma y el sello.
 * Aplica a todas las constancias que se descargan desde el portal del empleado.
 *
 * @property-read Schema $form
 */
class ManageEmploymentCertificateTemplate extends Page
{
    use ChecksHrAccess;

    protected static ?string $slug = 'plantilla-constancia-trabajo';

    protected static ?string $navigationLabel = 'Plantilla de constancia';

    protected static ?string $title = 'Plantilla de constancia de trabajo';

    protected static string|UnitEnum|null $navigationGroup = 'hr';

    protected static ?int $navigationSort = 16;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected string $view = 'filament-panels::pages.page';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(EmploymentCertificateSetting::current()->only([
            'title',
            'body',
            'city',
            'signer_name',
            'signer_title',
            'signature_path',
            'stamp_path',
            'footer',
        ]));
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Se carga una sola vez y aplica a todas las constancias. El sistema completa las variables con los datos de cada empleado al descargar.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Texto de la constancia')
                    ->icon(Heroicon::DocumentText)
                    ->description(new HtmlString(self::placeholdersHelp()))
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(120),
                        Textarea::make('body')
                            ->label('Cuerpo')
                            ->helperText('Separa los párrafos con una línea en blanco.')
                            ->required()
                            ->rows(12)
                            ->maxLength(6000),
                        Grid::make(2)->schema([
                            TextInput::make('city')
                                ->label('Ciudad de emisión')
                                ->placeholder('Caracas')
                                ->maxLength(120),
                            TextInput::make('footer')
                                ->label('Pie de página')
                                ->placeholder('Razón social, RIF, dirección y teléfonos')
                                ->maxLength(300),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Firma')
                    ->icon(Heroicon::PencilSquare)
                    ->description('Quien firma todas las constancias. La firma y el sello se guardan en un disco privado.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('signer_name')
                                ->label('Nombre del firmante')
                                ->required()
                                ->maxLength(160),
                            TextInput::make('signer_title')
                                ->label('Cargo del firmante')
                                ->placeholder('Gerente de Recursos Humanos')
                                ->maxLength(160),
                            FileUpload::make('signature_path')
                                ->label('Firma')
                                ->helperText('PNG con fondo transparente, ideal.')
                                ->image()
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                ->disk(EmploymentCertificatePdfFactory::ASSETS_DISK)
                                ->directory('employment-certificate')
                                ->visibility('private')
                                ->maxSize(2048),
                            FileUpload::make('stamp_path')
                                ->label('Sello')
                                ->image()
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                ->disk(EmploymentCertificatePdfFactory::ASSETS_DISK)
                                ->directory('employment-certificate')
                                ->visibility('private')
                                ->maxSize(2048),
                        ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Guardar plantilla')
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        EmploymentCertificateSetting::current()->fill([
            'title' => trim((string) $data['title']),
            'body' => trim((string) $data['body']),
            'city' => filled($data['city'] ?? null) ? trim((string) $data['city']) : null,
            'signer_name' => trim((string) $data['signer_name']),
            'signer_title' => filled($data['signer_title'] ?? null) ? trim((string) $data['signer_title']) : null,
            'signature_path' => $data['signature_path'] ?? null,
            'stamp_path' => $data['stamp_path'] ?? null,
            'footer' => filled($data['footer'] ?? null) ? trim((string) $data['footer']) : null,
        ])->save();

        Notification::make()
            ->title('Plantilla guardada')
            ->body('Las próximas constancias usarán este texto y esta firma.')
            ->success()
            ->send();
    }

    private static function placeholdersHelp(): string
    {
        $items = collect(EmploymentCertificateSetting::PLACEHOLDERS)
            ->map(fn (string $description, string $placeholder): string => '<code>'.e($placeholder).'</code> '.e($description))
            ->implode(' · ');

        return 'Variables disponibles: '.$items;
    }
}
