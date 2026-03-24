<?php

namespace App\Filament\Resources\ApiClients\Pages;

use App\Filament\Resources\ApiClients\ApiClientResource;
use App\Filament\Resources\ApiClients\Widgets\ApiClientTokenBanner;
use App\Models\ApiClient;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewApiClient extends ViewRecord
{
    protected static string $resource = ApiClientResource::class;

    public ?string $revealedPlainToken = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->revealedPlainToken = session()->pull('filament_api_client_plain_token');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerateToken')
                ->label('Regenerar token')
                ->icon(Heroicon::ArrowPath)
                ->color('warning')
                ->modalWidth('md')
                ->modalHeading('¿Regenerar el token de acceso?')
                ->modalDescription('El token actual dejará de funcionar de inmediato. Deberás entregar el nuevo valor al aliado.')
                ->modalSubmitActionLabel('Sí, generar nuevo token')
                ->requiresConfirmation()
                ->action(function (): void {
                    $plain = ApiClient::generatePlainToken();

                    $this->record->update([
                        'token_hash' => ApiClient::hashToken($plain),
                    ]);

                    $this->revealedPlainToken = $plain;

                    Notification::make()
                        ->title('Nuevo token generado')
                        ->body('Cópialo desde el banner superior; no se mostrará de nuevo al salir de esta página.')
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ApiClientTokenBanner::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'revealedPlainToken' => $this->revealedPlainToken,
        ];
    }
}
