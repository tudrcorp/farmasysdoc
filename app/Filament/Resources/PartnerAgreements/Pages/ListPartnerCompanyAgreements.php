<?php

namespace App\Filament\Resources\PartnerAgreements\Pages;

use App\Enums\PartnerAgreementStatus;
use App\Filament\Resources\PartnerAgreements\PartnerCompanyAgreementResource;
use App\Filament\Resources\PartnerAgreements\Widgets\PartnerAgreementsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListPartnerCompanyAgreements extends ListRecords
{
    protected static string $resource = PartnerCompanyAgreementResource::class;

    protected static ?string $title = 'Convenios y renovaciones';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo convenio')
                ->icon(Heroicon::Plus),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PartnerAgreementsOverview::class,
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $base = fn (): Builder => PartnerCompanyAgreementResource::getEloquentQuery();
        $statuses = fn (PartnerAgreementStatus ...$statuses): array => array_map(fn (PartnerAgreementStatus $status): string => $status->value, $statuses);

        return [
            'all' => Tab::make('Todos'),
            'pending' => Tab::make('Por aprobar')
                ->icon(Heroicon::Clock)
                ->badge(fn (): int => $base()->where('status', PartnerAgreementStatus::PendingApproval->value)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PartnerAgreementStatus::PendingApproval->value)),
            'expiring' => Tab::make('Por vencer (60 días)')
                ->icon(Heroicon::ExclamationTriangle)
                ->badge(fn (): int => $base()->expiringWithin(60)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->expiringWithin(60)),
            'active' => Tab::make('Vigentes')
                ->icon(Heroicon::CheckCircle)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', $statuses(PartnerAgreementStatus::Active, PartnerAgreementStatus::Approved))),
            'drafts' => Tab::make('Borradores y rechazados')
                ->icon(Heroicon::PencilSquare)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', $statuses(PartnerAgreementStatus::Draft, PartnerAgreementStatus::Rejected))),
            'expired' => Tab::make('Vencidos')
                ->icon(Heroicon::XCircle)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PartnerAgreementStatus::Expired->value)),
            'closed' => Tab::make('Historial')
                ->icon(Heroicon::ArchiveBox)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', $statuses(PartnerAgreementStatus::Superseded, PartnerAgreementStatus::Cancelled))),
        ];
    }
}
