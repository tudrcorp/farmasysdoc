<?php

namespace App\Filament\Resources\Hr\Severance\Settlements\Pages;

use App\Filament\Resources\Hr\Severance\Settlements\Actions\EmployeeSettlementActions;
use App\Filament\Resources\Hr\Severance\Settlements\EmployeeSettlementResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeSettlement extends ViewRecord
{
    protected static string $resource = EmployeeSettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EmployeeSettlementActions::submit(),
            EmployeeSettlementActions::approve(),
            EmployeeSettlementActions::pay(),
            EmployeeSettlementActions::recalculate(),
            EmployeeSettlementActions::adjustLine(),
            EmployeeSettlementActions::returnToDraft(),
            EmployeeSettlementActions::pdf(),
            EmployeeSettlementActions::cancel(),
        ];
    }
}
