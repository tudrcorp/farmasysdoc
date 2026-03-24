<?php

namespace App\Filament\Resources\ApiClients\Pages;

use App\Filament\Resources\ApiClients\ApiClientResource;
use App\Filament\Resources\ApiClients\Concerns\NormalizesApiClientAllowedIps;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditApiClient extends EditRecord
{
    use NormalizesApiClientAllowedIps;

    protected static string $resource = ApiClientResource::class;

    protected static ?string $title = 'Editar cliente API';

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
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeAllowedIpsInFormData($data);
    }
}
