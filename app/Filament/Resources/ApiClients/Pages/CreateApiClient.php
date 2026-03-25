<?php

namespace App\Filament\Resources\ApiClients\Pages;

use App\Filament\Resources\ApiClients\ApiClientResource;
use App\Filament\Resources\ApiClients\Concerns\NormalizesApiClientAllowedIps;
use App\Models\ApiClient;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreateApiClient extends CreateRecord
{
    use NormalizesApiClientAllowedIps;

    protected static string $resource = ApiClientResource::class;

    protected static ?string $title = 'Nuevo cliente API';

    protected ?string $plainToken = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->normalizeAllowedIpsInFormData($data);

        $this->plainToken = ApiClient::generatePlainToken();
        $data['token_hash'] = ApiClient::hashToken($this->plainToken);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->plainToken !== null) {
            session()->flash('filament_api_client_plain_token', $this->plainToken);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--gray',
                ])
                ->url(route('filament.farmaadmin.resources.api-clients.index')),
        ];
    }
}
