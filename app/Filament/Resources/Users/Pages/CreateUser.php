<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @var list<int>|null
     */
    protected ?array $pendingGerenciaBranchIdsForSync = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingGerenciaBranchIdsForSync = User::extractGerenciaManagedBranchIdsFromData($data);
        $data = User::stripBranchIdWhenDeliveryRole($data);

        return User::normalizeGerenciaBranchFields($data);
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        if (! $record instanceof User) {
            return;
        }

        $ids = $this->pendingGerenciaBranchIdsForSync ?? [];
        $this->pendingGerenciaBranchIdsForSync = null;

        if ($record->hasGerenciaRole()) {
            $record->managedBranches()->sync($ids);
        } else {
            $record->managedBranches()->detach();
        }
    }
}
