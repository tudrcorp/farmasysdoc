<?php

namespace App\Filament\Resources\PhysicalCashBoxCloseReports\Pages;

use App\Filament\Resources\PhysicalCashBoxCloseReports\PhysicalCashBoxCloseReportResource;
use App\Models\PhysicalCashBoxCloseReport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewPhysicalCashBoxCloseReport extends ViewRecord
{
    protected static string $resource = PhysicalCashBoxCloseReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Descargar PDF')
                ->icon(Heroicon::DocumentArrowDown)
                ->visible(fn (): bool => filled($this->reportPdfPath()))
                ->action(fn (): ?StreamedResponse => $this->downloadStoredFile(
                    $this->reportPdfPath(),
                    'cierre-caja-'.$this->getRecord()->getKey().'.pdf',
                    'application/pdf',
                )),
            Action::make('downloadUsdPhoto')
                ->label('Foto efectivo USD')
                ->icon(Heroicon::Camera)
                ->visible(fn (): bool => filled($this->getRecord()->close_usd_cash_photo_path))
                ->action(fn (): ?StreamedResponse => $this->downloadStoredFile(
                    (string) $this->getRecord()->close_usd_cash_photo_path,
                    'cierre-caja-usd-'.$this->getRecord()->getKey().'.jpg',
                    'image/jpeg',
                )),
            Action::make('downloadPosPhoto')
                ->label('Foto cierre POS')
                ->icon(Heroicon::Photo)
                ->visible(fn (): bool => filled($this->getRecord()->close_pos_receipt_photo_path))
                ->action(fn (): ?StreamedResponse => $this->downloadStoredFile(
                    (string) $this->getRecord()->close_pos_receipt_photo_path,
                    'cierre-caja-pos-'.$this->getRecord()->getKey().'.jpg',
                    'image/jpeg',
                )),
        ];
    }

    private function reportPdfPath(): ?string
    {
        $path = $this->getRecord()->pdf_path;

        return filled($path) ? (string) $path : null;
    }

    private function downloadStoredFile(string $path, string $filename, string $mime): ?StreamedResponse
    {
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            Notification::make()
                ->title('Archivo no disponible')
                ->body('El archivo no está en el almacenamiento local.')
                ->warning()
                ->send();

            return null;
        }

        return Storage::disk('local')->download($path, $filename, [
            'Content-Type' => $mime,
        ]);
    }

    public function getRecord(): PhysicalCashBoxCloseReport
    {
        /** @var PhysicalCashBoxCloseReport $record */
        $record = parent::getRecord();

        return $record;
    }
}
