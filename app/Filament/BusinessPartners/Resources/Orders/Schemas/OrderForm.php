<?php

namespace App\Filament\BusinessPartners\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\Schemas\OrderForm as SharedOrderForm;
use Filament\Schemas\Schema;

/**
 * Mismo esquema que el panel Farmaadmin: pedido aliado (cliente oculto, compañía por defecto, ítems, etc.).
 */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return SharedOrderForm::configure($schema);
    }
}
