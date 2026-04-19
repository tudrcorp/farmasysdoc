<?php

namespace App\Filament\Resources\Rols\Schemas;

use App\Models\User;
use App\Support\Filament\FarmaadminMenuAccessCatalog;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RolForm
{
    /**
     * @var array<string, string>
     */
    private const GROUP_STATE_PATHS = [
        'menu_group_general' => 'General',
        'menu_group_operaciones' => 'Operaciones',
        'menu_group_aliados' => 'Aliados Comerciales',
        'menu_group_inventario' => 'Inventario',
        'menu_group_configuracion' => 'Configuración',
        'menu_group_marketing' => 'Marketing',
    ];

    /**
     * @var array<string, array{description: string, icon: Heroicon}>
     */
    private const GROUP_META = [
        'General' => [
            'description' => 'Accesos globales del panel.',
            'icon' => Heroicon::Home,
        ],
        'Operaciones' => [
            'description' => 'Flujos diarios de ventas, compras y pedidos.',
            'icon' => Heroicon::ClipboardDocumentList,
        ],
        'Aliados Comerciales' => [
            'description' => 'Gestión de aliados y módulos vinculados.',
            'icon' => Heroicon::BuildingOffice2,
        ],
        'Inventario' => [
            'description' => 'Existencias, movimientos y abastecimiento.',
            'icon' => Heroicon::ArchiveBox,
        ],
        'Configuración' => [
            'description' => 'Parámetros administrativos y de sistema.',
            'icon' => Heroicon::Cog6Tooth,
        ],
        'Marketing' => [
            'description' => 'Campañas, contenidos y segmentación.',
            'icon' => Heroicon::Megaphone,
        ],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del rol')
                    ->description('Define el nombre funcional del rol y su estado operativo.')
                    ->icon(Heroicon::Identification)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre del rol')
                                    ->required()
                                    ->maxLength(120)
                                    ->unique(ignoreRecord: true)
                                    ->dehydrateStateUsing(fn (?string $state): string => mb_strtoupper(trim((string) $state)))
                                    ->placeholder('Ej. VENTAS, CAJA, MARKETING')
                                    ->helperText('Se recomienda usar un nombre corto, único y en mayúsculas.')
                                    ->columnSpan(1),
                                Toggle::make('is_active')
                                    ->label('Rol activo')
                                    ->default(true)
                                    ->helperText('Solo roles activos estarán disponibles al crear o editar usuarios.')
                                    ->columnSpan(1),
                            ]),
                        TextInput::make('description')
                            ->label('Descripción')
                            ->maxLength(255)
                            ->placeholder('Ej. Equipo comercial con acceso a ventas, clientes y caja.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Permisos de menú (experiencia iOS agrupada)')
                    ->description('El administrador decide qué módulos verá este rol. Los usuarios heredan estos permisos desde su rol.')
                    ->icon(Heroicon::SquaresPlus)
                    ->schema(self::groupedPermissionCheckboxes())
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function hydrateGroupedPermissions(array $data): array
    {
        $allowed = $data['allowed_menu_items'] ?? User::defaultAllowedMenuItems();

        if (! is_array($allowed)) {
            $allowed = User::defaultAllowedMenuItems();
        }

        foreach (self::GROUP_STATE_PATHS as $statePath => $group) {
            $groupKeys = array_keys(self::assignableOptionsForGroup($group));
            $data[$statePath] = array_values(array_intersect($allowed, $groupKeys));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function collapseGroupedPermissions(array $data): array
    {
        if (strtoupper((string) ($data['name'] ?? '')) === 'ADMINISTRADOR') {
            $data['allowed_menu_items'] = User::defaultAllowedMenuItems();
        } else {
            $resolved = [];

            foreach (array_keys(self::GROUP_STATE_PATHS) as $statePath) {
                $selected = $data[$statePath] ?? [];

                if (! is_array($selected)) {
                    continue;
                }

                $resolved = [...$resolved, ...$selected];
            }

            $data['allowed_menu_items'] = array_values(array_unique($resolved));
        }

        foreach (array_keys(self::GROUP_STATE_PATHS) as $statePath) {
            unset($data[$statePath]);
        }

        return $data;
    }

    /**
     * @return array<int, Component>
     */
    private static function groupedPermissionCheckboxes(): array
    {
        $cards = [];

        foreach (self::GROUP_STATE_PATHS as $statePath => $group) {
            $options = self::assignableOptionsForGroup($group);

            if ($options === []) {
                continue;
            }

            $meta = self::GROUP_META[$group] ?? null;

            $cards[] = Section::make($group)
                ->description($meta['description'] ?? 'Permisos del grupo.')
                ->icon($meta['icon'] ?? Heroicon::SquaresPlus)
                ->schema([
                    CheckboxList::make($statePath)
                        ->label('Módulos permitidos')
                        ->options($options)
                        ->columns(1)
                        ->bulkToggleable()
                        ->gridDirection('row')
                        ->helperText('Marca solo los módulos que este rol puede ver en el menú.')
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->columnSpanFull();
        }

        return [
            Grid::make([
                'default' => 1,
                'xl' => 2,
            ])
                ->schema($cards),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function assignableOptionsForGroup(string $group): array
    {
        $options = FarmaadminMenuAccessCatalog::optionsForGroup($group);

        unset($options['dashboard']);

        return $options;
    }
}
