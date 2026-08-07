<?php

namespace App\Filament\Resources\Rols\Pages;

use App\Filament\Resources\Rols\RolResource;
use App\Filament\Resources\Rols\Schemas\RolForm;
use App\Models\Rol;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRol extends EditRecord
{
    protected static string $resource = RolResource::class;

    protected static ?string $title = 'Editar rol';

    /**
     * @var list<int>|null
     */
    protected ?array $pendingBranchIdsForSync = null;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record instanceof Rol) {
            $record->loadMissing('branches');
            $data['branch_ids'] = $record->branches
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
        }

        return RolForm::hydrateGroupedPermissions($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingBranchIdsForSync = self::branchIdsToSync($data);
        $data = Rol::stripBranchIdsFromData($data);

        return RolForm::collapseGroupedPermissions($data);
    }

    protected function afterSave(): void
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
