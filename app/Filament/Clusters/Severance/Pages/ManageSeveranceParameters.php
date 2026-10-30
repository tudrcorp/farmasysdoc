<?php

namespace App\Filament\Clusters\Severance\Pages;

use App\Filament\Clusters\Severance\SeveranceCluster;
use App\Models\SeveranceSetting;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Parámetros de la liquidación. Cada liquidación guarda su propia copia,
 * así que un cambio solo aplica a las que se creen después.
 *
 * @property-read Schema $form
 */
class ManageSeveranceParameters extends Page
{
    protected static ?string $cluster = SeveranceCluster::class;

    protected static ?string $slug = 'parametros';

    protected static ?string $navigationLabel = 'Parámetros legales';

    protected static ?string $title = 'Parámetros legales';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AdjustmentsHorizontal;

    protected string $view = 'filament-panels::pages.page';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }

    public function mount(): void
    {
        $this->form->fill(SeveranceSetting::current()->toParameters());
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Parten de los mínimos de la LOTTT. Súbelos si la empresa paga más. Solo afectan a las liquidaciones nuevas.';
    }

    public function form(Schema $schema): Schema
    {
        $days = fn (string $name, string $label, int $minimum): TextInput => TextInput::make($name)
            ->label($label)
            ->numeric()
            ->integer()
            ->minValue($minimum)
            ->maxValue(365)
            ->required()
            ->suffix('días');

        return $schema
            ->statePath('data')
            ->components([
                Form::make([
                    Section::make('Utilidades')
                        ->icon(Heroicon::Banknotes)
                        ->schema([
                            $days('utilities_days_per_year', 'Días de utilidades por año', 30),
                        ]),
                    Section::make('Vacaciones y bono vacacional')
                        ->description('Se suma 1 día por cada año de servicio, hasta el máximo.')
                        ->icon(Heroicon::Sun)
                        ->schema([
                            Grid::make(2)->schema([
                                $days('vacation_base_days', 'Vacaciones: días base', 15),
                                $days('vacation_max_days', 'Vacaciones: máximo', 30),
                                $days('vacation_bonus_base_days', 'Bono vacacional: días base', 15),
                                $days('vacation_bonus_max_days', 'Bono vacacional: máximo', 30),
                            ]),
                        ]),
                    Actions::make([
                        Action::make('save')->label('Guardar')->submit('save'),
                    ]),
                ])
                    ->id('severance-parameters')
                    ->livewireSubmitHandler('save'),
            ]);
    }

    public function save(): void
    {
        SeveranceSetting::current()->update($this->form->getState());

        Notification::make()->title('Parámetros guardados')->success()->send();
    }
}
