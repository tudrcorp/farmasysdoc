<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Enums\PlanStatus;
use App\Filament\Resources\Plans\PlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected static ?string $title = 'Planes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo plan')
                ->icon(Heroicon::Plus),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $count = fn (PlanStatus $status): int => PlanResource::getEloquentQuery()->where('status', $status->value)->count();

        return [
            'all' => Tab::make('Todos'),
            'active' => Tab::make('Activos')
                ->icon(Heroicon::CheckCircle)
                ->badge(fn (): int => $count(PlanStatus::Active))
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PlanStatus::Active->value)),
            'drafts' => Tab::make('Borradores')
                ->icon(Heroicon::PencilSquare)
                ->badge(fn (): int => $count(PlanStatus::Draft))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PlanStatus::Draft->value)),
            'inactive' => Tab::make('Inactivos')
                ->icon(Heroicon::XCircle)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PlanStatus::Inactive->value)),
        ];
    }
}
