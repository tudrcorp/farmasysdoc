<?php

namespace App\Filament\Resources\PartnerCompanies\Pages;

use App\Enums\UncoveredMedicineSyncStatus;
use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Filament\Resources\PartnerCompanies\Tables\UncoveredMedicinesTable;
use App\Filament\Resources\PartnerCompanies\Widgets\UncoveredMedicineInventoryStats;
use App\Filament\Resources\PartnerCompanies\Widgets\UncoveredMedicineManagementStats;
use App\Filament\Resources\PartnerCompanies\Widgets\UncoveredMedicineRankingWidget;
use App\Models\PartnerCompany;
use App\Models\PartnerUncoveredMedicine;
use App\Models\PartnerUncoveredMedicineConnection;
use App\Services\Partners\UncoveredMedicineApiException;
use App\Services\Partners\UncoveredMedicineSync;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ManagePartnerUncoveredMedicines extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = PartnerCompanyResource::class;

    protected static ?string $title = 'Gestión de medicamentos';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.resources.partner-companies.pages.manage-uncovered-medicines';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $company = $this->company();

        abort_unless(PartnerCompanyResource::canView($company), 403);
        abort_unless($company->hasUncoveredMedicinesAccess(), 404);
    }

    public function getHeading(): string|Htmlable
    {
        return 'Medicamentos no cubiertos';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $company = $this->company();
        $connection = $this->connection();
        $parts = [$company->trade_name ?: $company->legal_name];

        if ($connection?->isSyncActive()) {
            $parts[] = 'Actualización en curso. La tabla sigue mostrando la última copia guardada.';
        } elseif ($connection?->last_synced_at !== null) {
            $parts[] = 'Última actualización: '.$connection->last_synced_at->timezone(config('app.timezone'))->format('d/m/Y h:i a');
        } else {
            $parts[] = 'Todavía no se ha consultado a Tu Dr. Group.';
        }

        if ($connection?->rate_limit_remaining !== null) {
            $quota = (string) $connection->rate_limit_remaining;
            if ($connection->rate_limit_limit !== null) {
                $quota .= ' de '.$connection->rate_limit_limit;
            }
            $parts[] = 'Cupo restante: '.$quota.' por minuto';
        }

        if (filled($connection?->last_error) && ! $connection->isSyncActive()) {
            $parts[] = 'Último error: '.$connection->last_error;
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'partnerCompanyId' => (int) $this->company()->getKey(),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            UncoveredMedicineInventoryStats::class,
            UncoveredMedicineManagementStats::class,
            UncoveredMedicineRankingWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function isSyncRunning(): bool
    {
        return $this->connection()?->isSyncActive() ?? false;
    }

    public function table(Table $table): Table
    {
        return UncoveredMedicinesTable::configure($table)
            ->query(PartnerUncoveredMedicine::query()->forPartner((int) $this->company()->getKey()));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver a la ficha')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->url(fn (): string => PartnerCompanyResource::getUrl('view', ['record' => $this->company()])),
            Action::make('refreshUncoveredMedicines')
                ->label('Actualizar información')
                ->icon(Heroicon::ArrowPath)
                ->color('primary')
                ->disabled(fn (): bool => $this->isSyncRunning())
                ->requiresConfirmation()
                ->modalHeading('Actualizar medicamentos no cubiertos')
                ->modalDescription('Se consultará a Tu Dr. Group página por página. Esto consume parte del cupo de peticiones por minuto. La tabla no cambia hasta que la actualización termine, y si falla se conserva la copia anterior.')
                ->modalSubmitActionLabel('Actualizar ahora')
                ->action(function (): void {
                    $company = $this->company();

                    try {
                        $queued = app(UncoveredMedicineSync::class)->queue($company, Auth::id());
                    } catch (UncoveredMedicineApiException $exception) {
                        Notification::make()
                            ->title('No se pudo iniciar la actualización')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->title('No se pudo iniciar la actualización')
                            ->body('Intente de nuevo en unos minutos.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $company->unsetRelation('uncoveredMedicineConnection');
                    $connection = $company->uncoveredMedicineConnection;

                    if (! $queued && $connection?->isSyncActive()) {
                        Notification::make()
                            ->title('Ya hay una actualización en curso')
                            ->warning()
                            ->send();

                        return;
                    }

                    if ($connection?->sync_status === UncoveredMedicineSyncStatus::Succeeded) {
                        Notification::make()
                            ->title('Medicamentos actualizados')
                            ->success()
                            ->send();

                        return;
                    }

                    if ($connection?->sync_status === UncoveredMedicineSyncStatus::Failed) {
                        Notification::make()
                            ->title('No se pudo actualizar la información')
                            ->body($connection->last_error ?: 'Intente de nuevo.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Actualización en curso')
                        ->body('Puede dejar esta página abierta. La tabla se refresca al terminar y no volverá a llamar a Tu Dr. Group hasta el próximo clic.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function company(): PartnerCompany
    {
        /** @var PartnerCompany $company */
        $company = $this->getRecord();

        return $company;
    }

    public function connection(): ?PartnerUncoveredMedicineConnection
    {
        return $this->company()->uncoveredMedicineConnection;
    }
}
