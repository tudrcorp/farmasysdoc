<?php

namespace App\Filament\Resources\OtpCodes\Pages;

use App\Filament\Resources\OtpCodes\OtpCodeResource;
use Filament\Resources\Pages\ListRecords;

class ListOtpCodes extends ListRecords
{
    protected static string $resource = OtpCodeResource::class;

    protected static ?string $title = 'Claves OTP';

    public function getSubheading(): ?string
    {
        return 'Claves generadas por el sistema para autorizar operaciones. Cada clave vale 10 minutos y se anula al usarse. No se muestran las claves que usted mismo solicitó.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
