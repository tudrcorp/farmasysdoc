<?php

namespace App\Filament\Resources\FiscalPrinters\Actions;

use App\Models\FiscalPrinter;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Fiscal\FiscalAgentInstallGuide;
use App\Support\Fiscal\FiscalAgentPackage;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class FiscalAgentPackageActions
{
    /**
     * Instrucciones + descarga del instalador. En la ficha de una máquina, el comando trae su puerto COM.
     */
    public static function download(): Action
    {
        return Action::make('fiscalAgentDownload')
            ->label('Agente Windows')
            ->icon(Heroicon::ArrowDownTray)
            ->color('gray')
            ->modalHeading('Agente Windows de máquina fiscal')
            ->modalWidth('3xl')
            ->modalContent(fn (?FiscalPrinter $record = null) => FiscalAgentInstallGuide::html($record))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions([
                Action::make('downloadPackage')
                    ->label('Descargar instalador (.zip)')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('primary')
                    ->visible(fn (): bool => FiscalAgentPackage::current() !== null)
                    ->url(route('fiscal-agent.download')),
            ]);
    }

    public static function upload(): Action
    {
        return Action::make('fiscalAgentUpload')
            ->label('Subir versión del agente')
            ->icon(Heroicon::ArrowUpTray)
            ->color('gray')
            ->visible(fn (): bool => Auth::user() instanceof User && Auth::user()->isAdministrator())
            ->modalHeading('Subir versión del agente Windows')
            ->modalDescription('Suba el .zip generado (FarmadocFiscalAgent-X.Y.Z.zip). Reemplaza la versión que se descarga desde aquí; los agentes ya instalados no se actualizan solos.')
            ->schema([
                FileUpload::make('package')
                    ->label('Paquete .zip')
                    ->disk(FiscalAgentPackage::disk())
                    ->directory('fiscal-agent/uploads')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'application/octet-stream'])
                    ->maxSize(51200)
                    ->storeFileNamesIn('package_original_name')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $user = Auth::user();
                $path = (string) $data['package'];
                $originalName = (string) ($data['package_original_name'] ?? $path);

                try {
                    $meta = FiscalAgentPackage::publish($path, $originalName, (string) ($user?->email ?? $user?->name ?? 'sistema'));
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Paquete rechazado')
                        ->body(collect($e->errors())->flatten()->implode(' '))
                        ->danger()
                        ->send();

                    return;
                }

                AuditLogger::record(
                    'fiscal_agent_uploaded',
                    'Fiscal · Nueva versión del agente Windows '.$meta['version'],
                    properties: ['module' => 'fiscal', 'version' => $meta['version'], 'sha256' => $meta['sha256']],
                );

                Notification::make()
                    ->title('Agente '.$meta['version'].' publicado')
                    ->body('Ya se puede descargar desde «Agente Windows».')
                    ->success()
                    ->send();
            });
    }
}
