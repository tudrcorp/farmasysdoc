<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $user = $this->getRecord();
        if ($user instanceof User && filled($user->partner_company_id)) {
            $user->loadMissing(['partnerCompany']);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
