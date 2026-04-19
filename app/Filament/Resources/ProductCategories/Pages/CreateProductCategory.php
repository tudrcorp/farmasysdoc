<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateProductCategory extends CreateRecord
{
    protected static string $resource = ProductCategoryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['profit_percentage'] = $this->normalizeProfitPercentage($data['profit_percentage'] ?? null);
        $actorId = Auth::id();
        $data['created_by'] = $actorId !== null ? (string) $actorId : null;
        $data['updated_by'] = $actorId !== null ? (string) $actorId : null;
        $authUser = Auth::user();

        if ($authUser instanceof User && $authUser->isManager() && ! $authUser->isAdministrator()) {
            $data['is_active'] = false;
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
