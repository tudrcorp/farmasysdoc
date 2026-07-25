<?php

namespace App\Filament\Resources\Rols\Schemas;

use App\Models\Rol;
use App\Models\User;
use App\Support\Filament\FarmaadminMenuAccessCatalog;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RolInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rol')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nombre'),
                                TextEntry::make('description')
                                    ->label('Descripción')
                                    ->placeholder('—'),
                                IconEntry::make('is_active')
                                    ->label('Activo')
                                    ->boolean(),
                                TextEntry::make('updated_at')
                                    ->label('Actualizado')
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Ítems de menú permitidos')
                    ->description('Módulos que este rol puede ver en Farmaadmin, agrupados como en la edición del rol.')
                    ->icon(Heroicon::SquaresPlus)
                    ->schema([
                        SchemaView::make('filament.infolists.components.rol-allowed-menu-items')
                            ->columnSpanFull()
                            ->viewData(fn (Rol $record): array => self::permissionViewData($record)),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array{
     *     groups: list<array{name: string, count: int, items: list<string>}>,
     *     total: int,
     *     isFullAccess: bool,
     *     isEmpty: bool
     * }
     */
    private static function permissionViewData(Rol $record): array
    {
        $catalog = FarmaadminMenuAccessCatalog::items();
        $allowed = $record->allowed_menu_items;

        if ($allowed === null) {
            $allowed = User::defaultAllowedMenuItems();
        }

        if (! is_array($allowed) || $allowed === []) {
            return [
                'groups' => [],
                'total' => 0,
                'isFullAccess' => false,
                'isEmpty' => true,
            ];
        }

        $allowed = array_values(array_unique(array_filter(
            $allowed,
            fn (mixed $key): bool => is_string($key) && $key !== '',
        )));

        $byGroup = [];

        foreach ($allowed as $key) {
            $meta = $catalog[$key] ?? null;
            $group = is_array($meta) ? (string) ($meta['group'] ?? 'Otros') : 'Otros';
            $label = is_array($meta) ? (string) ($meta['label'] ?? $key) : $key;
            $byGroup[$group][] = $label;
        }

        $groupOrder = array_keys(FarmaadminMenuAccessCatalog::groupedOptions());
        $groups = [];

        foreach ($groupOrder as $groupName) {
            if (! isset($byGroup[$groupName])) {
                continue;
            }

            $items = collect($byGroup[$groupName])->unique()->sort()->values()->all();

            $groups[] = [
                'name' => $groupName,
                'count' => count($items),
                'items' => $items,
            ];

            unset($byGroup[$groupName]);
        }

        foreach (collect($byGroup)->sortKeys() as $groupName => $labels) {
            $items = collect($labels)->unique()->sort()->values()->all();

            $groups[] = [
                'name' => (string) $groupName,
                'count' => count($items),
                'items' => $items,
            ];
        }

        $total = collect($groups)->sum('count');

        return [
            'groups' => $groups,
            'total' => $total,
            'isFullAccess' => $total >= count(FarmaadminMenuAccessCatalog::allKeys()),
            'isEmpty' => $total === 0,
        ];
    }
}
