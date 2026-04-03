<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Resources\Pages\CreateRecord;

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
        $actorId = auth()->id();
        $data['created_by'] = $actorId !== null ? (string) $actorId : null;
        $data['updated_by'] = $actorId !== null ? (string) $actorId : null;

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
