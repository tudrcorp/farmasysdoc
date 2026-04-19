<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditProductCategory extends EditRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Ver')
                ->icon(Heroicon::Eye),
            DeleteAction::make()
                ->label('Eliminar')
                ->icon(Heroicon::Trash),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['profit_percentage'] = $this->normalizeProfitPercentage($data['profit_percentage'] ?? null);
        $actorId = Auth::id();
        $data['updated_by'] = $actorId !== null ? (string) $actorId : null;
        $authUser = Auth::user();
        $record = $this->getRecord();

        if (
            $authUser instanceof User
            && $authUser->isManager()
            && ! $authUser->isAdministrator()
            && $record instanceof ProductCategory
        ) {
            $data['is_active'] = (bool) $record->is_active;
        }

        return $data;
    }

    private function normalizeProfitPercentage(mixed $value): float
    {
        if ($value === '' || $value === null) {
            return 0.0;
        }

        return (float) $value;
    }
}
