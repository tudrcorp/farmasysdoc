<?php

namespace App\Filament\Resources\PettyCashFunds\Pages;

use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use App\Filament\Resources\PettyCashFunds\Support\PettyCashFundActions;
use App\Models\PettyCashFund;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPettyCashFund extends ViewRecord
{
    protected static string $resource = PettyCashFundResource::class;

    protected static ?string $title = 'Detalle de caja chica';

    public function getTitle(): string
    {
        $record = $this->getRecord();

        if ($record instanceof PettyCashFund) {
            return $record->withReplenishmentQualifier(static::$title ?? 'Detalle de caja chica');
        }

        return static::$title ?? 'Detalle de caja chica';
    }

    protected function getHeaderActions(): array
    {
        return [
            PettyCashFundActions::approve()->record($this->getRecord()),
            PettyCashFundActions::reject()->record($this->getRecord()),
            PettyCashFundActions::registerExpense()->record($this->getRecord()),
            PettyCashFundActions::replenish()->record($this->getRecord()),
            EditAction::make()
                ->visible(fn (): bool => PettyCashFundResource::canEdit($this->getRecord())),
        ];
    }
}
