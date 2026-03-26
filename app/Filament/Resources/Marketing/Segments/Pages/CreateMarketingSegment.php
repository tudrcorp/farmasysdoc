<?php

namespace App\Filament\Resources\Marketing\Segments\Pages;

use App\Filament\Resources\Marketing\Segments\MarketingSegmentResource;
use Filament\Resources\Pages\CreateRecord;
use JsonException;

class CreateMarketingSegment extends CreateRecord
{
    protected static string $resource = MarketingSegmentResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['rules'] = $this->decodeRules($data);

        unset($data['rules_json']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function decodeRules(array $data): array
    {
        $raw = $data['rules_json'] ?? '{}';
        if (is_array($raw)) {
            return $raw;
        }

        try {
            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
