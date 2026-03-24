<?php

namespace App\Filament\Resources\OrderServices\Pages;

use App\Filament\Resources\OrderServices\OrderServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrderService extends EditRecord
{
    protected static string $resource = OrderServiceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $items = $data['items'] ?? null;

        if (is_array($items)) {
            $data['items'] = collect($items)
                ->map(function (mixed $row): array {
                    if (is_string($row)) {
                        return [
                            'name' => $row,
                            'indicacion' => '',
                        ];
                    }

                    if (is_array($row)) {
                        return [
                            'name' => (string) ($row['name'] ?? ''),
                            'indicacion' => (string) ($row['indicacion'] ?? ''),
                        ];
                    }

                    return [
                        'name' => '',
                        'indicacion' => '',
                    ];
                })
                ->filter(fn (array $row): bool => $row['name'] !== '')
                ->values()
                ->all();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
