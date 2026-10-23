<?php

namespace App\Filament\Resources\Hr\EmployeeRequests\Pages;

use App\Enums\EmployeeRequestStatus;
use App\Filament\Pages\Hr\ManageEmploymentCertificateTemplate;
use App\Filament\Resources\Hr\EmployeeRequests\EmployeeRequestResource;
use App\Models\EmployeeRequest;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListEmployeeRequests extends ListRecords
{
    protected static string $resource = EmployeeRequestResource::class;

    protected static ?string $title = 'Solicitudes de empleados';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('certificateTemplate')
                ->label('Plantilla de constancia')
                ->icon(Heroicon::DocumentText)
                ->color('gray')
                ->url(fn (): string => ManageEmploymentCertificateTemplate::getUrl())
                ->visible(fn (): bool => ManageEmploymentCertificateTemplate::canAccess()),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Por aprobar')
                ->badge(EmployeeRequest::query()->where('status', EmployeeRequestStatus::Pending)->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', EmployeeRequestStatus::Pending)),
            'all' => Tab::make('Todas'),
        ];
    }
}
