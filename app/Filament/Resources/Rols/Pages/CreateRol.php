<?php

namespace App\Filament\Resources\Rols\Pages;

use App\Filament\Resources\Rols\RolResource;
use App\Filament\Resources\Rols\Schemas\RolForm;
use App\Models\Rol;
use Filament\Resources\Pages\CreateRecord;

class CreateRol extends CreateRecord
{
    protected static string $resource = RolResource::class;

    protected static ?string $title = 'Nuevo rol';

    /**
     * @var list<int>|null
     */
    protected ?array $pendingBranchIdsForSync = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingBranchIdsForSync = self::branchIdsToSync($data);
        $data = Rol::stripBranchIdsFromData($data);

        return RolForm::collapseGroupedPermissions($data);
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        if (! $record instanceof Rol) {
            return;
        }

        $ids = $this->pendingBranchIdsForSync ?? [];
        $this->pendingBranchIdsForSync = null;

        $record->branches()->sync($ids);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private static function branchIdsToSync(array $data): array
    {
        $name = mb_strtoupper(trim((string) ($data['name'] ?? '')));
        if (in_array($name, ['ADMINISTRADOR', 'DELIVERY'], true)) {
            return [];
        }

        return Rol::extractBranchIdsFromData($data);
    }
}
