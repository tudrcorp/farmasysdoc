<?php

namespace App\Filament\BusinessPartners\Resources\TeamUsers\Pages;

use App\Filament\BusinessPartners\Resources\TeamUsers\TeamUserResource;
use App\Models\PartnerCompany;
use App\Models\User;
use App\Services\Partners\PartnerPanelUserService;
use App\Support\Partners\PartnerPanel;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTeamUsers extends ManageRecords
{
    protected static string $resource = TeamUserResource::class;

    public function getSubheading(): ?string
    {
        return 'Personas de su empresa con acceso a dale · Aliados y lo que puede hacer cada una. Los administradores (★) los designa Farmadoc.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo usuario')
                ->icon('heroicon-m-user-plus')
                ->modalHeading('Nuevo usuario')
                ->modalDescription('Al guardar, le enviaremos un correo para que cree su clave.')
                ->modalSubmitActionLabel('Crear y enviar acceso')
                ->modalWidth('2xl')
                ->createAnother(false)
                ->successNotificationTitle('Usuario creado: le enviamos el acceso por correo')
                ->using(fn (array $data): User => TeamUserResource::runGuarded(function () use ($data): User {
                    $partner = PartnerCompany::query()->findOrFail(PartnerPanel::companyId());

                    return app(PartnerPanelUserService::class)->create($partner, [
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'permissions' => $data['partner_permissions'] ?? [],
                    ], actor: PartnerPanel::user());
                })),
        ];
    }
}
