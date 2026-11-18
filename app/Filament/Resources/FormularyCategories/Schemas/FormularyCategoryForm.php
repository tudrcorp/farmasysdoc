<?php

namespace App\Filament\Resources\FormularyCategories\Schemas;

use App\Models\ActiveIngredient;
use App\Models\FormularyCategory;
use App\Support\Products\ActiveIngredientCatalog;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FormularyCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Categoría')
                    ->description('Maestro común: la misma categoría se puede usar en varios catálogos. No guarda relación con las categorías de productos.')
                    ->icon(Heroicon::RectangleStack)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                TextInput::make('code')
                                    ->label('Código')
                                    ->default(fn (): string => 'CTG-'.str_pad((string) ((int) FormularyCategory::query()->max('id') + 1), 4, '0', STR_PAD_LEFT))
                                    ->required()
                                    ->maxLength(30)
                                    ->unique(ignoreRecord: true)
                                    ->validationMessages(['unique' => 'Ya existe una categoría con ese código.'])
                                    ->prefixIcon(Heroicon::Hashtag),
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->placeholder('Ej.: Antihipertensivos, Antibióticos, Analgésicos')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->validationMessages(['unique' => 'Ya existe una categoría con ese nombre.'])
                                    ->columnSpan(['md' => 2]),
                                Textarea::make('description')
                                    ->label('Descripción')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Toggle::make('is_active')
                                    ->label('Activa')
                                    ->helperText('Las categorías inactivas no se ofrecen al armar catálogos.')
                                    ->default(true)
                                    ->inline(false),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Presentaciones')
                    ->description('Cada fila es un principio activo con su concentración y forma (ej.: Ibuprofeno · 400 mg tabletas). Si el principio activo no existe, créelo desde el selector.')
                    ->icon(Heroicon::Beaker)
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->relationship('items')
                            ->orderColumn('sort')
                            ->addActionLabel('Agregar presentación')
                            ->columns(['default' => 1, 'md' => 2])
                            ->itemLabel(fn (array $state): ?string => self::itemLabel($state))
                            ->collapsible()
                            ->defaultItems(0)
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $duplicate = self::firstDuplicate(is_array($value) ? $value : []);
                                    if ($duplicate !== null) {
                                        $fail("La presentación «{$duplicate}» está repetida.");
                                    }
                                },
                            ])
                            ->schema([
                                Select::make('active_ingredient_id')
                                    ->label('Principio activo')
                                    ->options(fn (): array => ActiveIngredient::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Nombre del principio activo')
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->createOptionModalHeading('Nuevo principio activo')
                                    ->createOptionUsing(function (array $data): int {
                                        $name = ActiveIngredientCatalog::createUnique((string) ($data['name'] ?? ''));

                                        return (int) ActiveIngredient::query()->where('name', $name)->value('id');
                                    }),
                                TextInput::make('presentation')
                                    ->label('Presentación')
                                    ->placeholder('Ej.: 500 mg tabletas, 120 mg/5 mL jarabe')
                                    ->maxLength(150)
                                    ->live(onBlur: true)
                                    ->dehydrateStateUsing(fn (?string $state): string => trim((string) $state)),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function itemLabel(array $state): ?string
    {
        $name = filled($state['active_ingredient_id'] ?? null)
            ? ActiveIngredient::query()->whereKey($state['active_ingredient_id'])->value('name')
            : null;

        if ($name === null) {
            return 'Nueva presentación';
        }

        $presentation = trim((string) ($state['presentation'] ?? ''));

        return $presentation !== '' ? "{$name} · {$presentation}" : (string) $name;
    }

    /**
     * @param  array<array-key, mixed>  $items
     */
    private static function firstDuplicate(array $items): ?string
    {
        $seen = [];

        foreach ($items as $item) {
            if (! is_array($item) || blank($item['active_ingredient_id'] ?? null)) {
                continue;
            }

            $key = $item['active_ingredient_id'].'|'.mb_strtolower(trim((string) ($item['presentation'] ?? '')));

            if (isset($seen[$key])) {
                return (string) self::itemLabel($item);
            }

            $seen[$key] = true;
        }

        return null;
    }
}
