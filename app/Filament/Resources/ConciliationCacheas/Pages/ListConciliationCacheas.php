<?php

namespace App\Filament\Resources\ConciliationCacheas\Pages;

use App\Filament\Resources\ConciliationCacheas\ConciliationCacheaResource;
use App\Filament\Resources\ConciliationCacheas\Widgets\StatsListConciliationCacheaOverview;
use App\Support\Sales\PosPaymentMethodOptions;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListConciliationCacheas extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = ConciliationCacheaResource::class;

    protected static ?string $title = 'Conciliaciones Cachea';

    protected ?string $subheading = 'Ventas con pago Cachea en caja. Gerencia ve solo montos por cobrar de sus sucursales asignadas; el administrador puede marcar cobros recibidos con la acción masiva.';

    public function getHeading(): string|Htmlable
    {
        return PosPaymentMethodOptions::cacheaPageHeadingHtml(static::$title ?? 'Conciliaciones Cachea');
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            StatsListConciliationCacheaOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return ['@sm' => 1, '@md' => 2, '@lg' => 4];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
