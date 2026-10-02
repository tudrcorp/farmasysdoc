<?php

namespace App\Filament\Resources\FiscalPrinters\Pages;

use App\Enums\FiscalDocumentType;
use App\Filament\Resources\ApiClients\Widgets\ApiClientTokenBanner;
use App\Filament\Resources\FiscalPrinters\Actions\ChangeFiscalPrinterModeAction;
use App\Filament\Resources\FiscalPrinters\Actions\FiscalAgentPackageActions;
use App\Filament\Resources\FiscalPrinters\Actions\ReassignPendingDocumentsAction;
use App\Filament\Resources\FiscalPrinters\Actions\RequestFiscalReportAction;
use App\Filament\Resources\FiscalPrinters\FiscalPrinterResource;
use App\Models\FiscalPrinter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewFiscalPrinter extends ViewRecord
{
    public const PLAIN_TOKEN_SESSION_KEY = 'filament_fiscal_printer_plain_token';

    protected static string $resource = FiscalPrinterResource::class;

    protected static ?string $title = 'Máquina fiscal';

    public ?string $revealedPlainToken = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->revealedPlainToken = session()->pull(self::PLAIN_TOKEN_SESSION_KEY);
    }

    protected function getHeaderActions(): array
    {
        return [
            ChangeFiscalPrinterModeAction::make(),
            FiscalAgentPackageActions::download(),
            ReassignPendingDocumentsAction::make(),
            RequestFiscalReportAction::make(FiscalDocumentType::XReport),
            RequestFiscalReportAction::make(FiscalDocumentType::ZReport),
            Action::make('regenerateAgentToken')
                ->label('Regenerar token del agente')
                ->icon(Heroicon::ArrowPath)
                ->color('warning')
                ->modalWidth('md')
                ->modalHeading('¿Regenerar el token del agente?')
                ->modalDescription('El agente instalado en la PC de caja dejará de conectarse hasta que se configure con el nuevo token.')
                ->modalSubmitActionLabel('Sí, generar nuevo token')
                ->requiresConfirmation()
                ->action(function (): void {
                    $plain = FiscalPrinter::generatePlainToken();

                    $this->record->update([
                        'agent_token_hash' => FiscalPrinter::hashToken($plain),
                    ]);

                    $this->revealedPlainToken = $plain;

                    Notification::make()
                        ->title('Nuevo token generado')
                        ->body('Cópialo desde el banner superior y configúralo en el agente; no se mostrará de nuevo.')
                        ->success()
                        ->send();
                }),
            EditAction::make()
                ->label('Editar')
                ->icon(Heroicon::PencilSquare),
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
