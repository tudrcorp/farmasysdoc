<?php

namespace App\Filament\Resources\Marketing\Segments\Pages;

use App\Filament\Resources\Marketing\Segments\MarketingSegmentResource;
use Filament\Resources\Pages\EditRecord;
use JsonException;

class EditMarketingSegment extends EditRecord
{
    protected static string $resource = MarketingSegmentResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $rules = $data['rules'] ?? [];
        $data['rules_json'] = json_encode(
            is_array($rules) ? $rules : [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ) ?: '{}';

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $raw = $data['rules_json'] ?? '{}';
        try {
            $decoded = is_array($raw)
                ? $raw
                : json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = [];
        }
        $data['rules'] = is_array($decoded) ? $decoded : [];
        unset($data['rules_json']);

        return $data;
    }
}
