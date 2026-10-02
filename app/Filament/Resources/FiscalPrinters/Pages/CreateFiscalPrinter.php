<?php

namespace App\Filament\Resources\FiscalPrinters\Pages;

use App\Filament\Resources\FiscalPrinters\FiscalPrinterResource;
use App\Models\FiscalPrinter;
use Filament\Resources\Pages\CreateRecord;

class CreateFiscalPrinter extends CreateRecord
{
    protected static string $resource = FiscalPrinterResource::class;

    protected static ?string $title = 'Nueva máquina fiscal';

    protected ?string $plainToken = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->plainToken = FiscalPrinter::generatePlainToken();
        $data['agent_token_hash'] = FiscalPrinter::hashToken($this->plainToken);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->plainToken !== null) {
            session()->flash(ViewFiscalPrinter::PLAIN_TOKEN_SESSION_KEY, $this->plainToken);
        }
    }

    protected function getRedirectUrl(): string
    {
        return FiscalPrinterResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
