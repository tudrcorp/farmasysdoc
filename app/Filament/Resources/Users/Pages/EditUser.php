<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
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
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record instanceof User) {
            $record->loadMissing('managedBranches');
            $data['managed_branch_ids'] = $record->managedBranches
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingGerenciaBranchIdsForSync = User::extractGerenciaManagedBranchIdsFromData($data);

        return User::normalizeGerenciaBranchFields(User::stripBranchIdWhenDeliveryRole($data));
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        if (! $record instanceof User) {
            return;
        }

        $ids = $this->pendingGerenciaBranchIdsForSync ?? [];
        $this->pendingGerenciaBranchIdsForSync = null;

        $record->refresh();

        if ($record->hasGerenciaRole()) {
            $record->managedBranches()->sync($ids);
        } else {
            $record->managedBranches()->detach();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
