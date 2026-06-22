<?php

namespace App\Filament\Resources\BranchSalesGoals\Pages;

use App\Filament\Resources\BranchSalesGoals\BranchSalesGoalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListBranchSalesGoals extends ListRecords
{
    protected static string $resource = BranchSalesGoalResource::class;

    protected static ?string $title = 'Metas de ventas';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Carga las metas mensuales en dólares por sucursal y la meta global de la empresa.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva meta')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
