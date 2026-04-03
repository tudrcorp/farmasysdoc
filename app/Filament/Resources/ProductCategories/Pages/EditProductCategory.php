<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

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
        $actorId = auth()->id();
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
