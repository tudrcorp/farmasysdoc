<?php

namespace App\Filament\Resources\Hr\Severance\Exits\Pages;

use App\Filament\Resources\Hr\Severance\Exits\EmployeeExitResource;
use App\Models\Employee;
use App\Services\Severance\EmployeeExitService;
use App\Support\Filament\BranchAuthScope;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CreateEmployeeExit extends CreateRecord
{
    protected static string $resource = EmployeeExitResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        abort_unless(BranchAuthScope::apply(Employee::query())->whereKey($data['employee_id'])->exists(), 403);

        try {
            return app(EmployeeExitService::class)->open($data, EmployeeExitResource::currentUser());
        } catch (RuntimeException $exception) {
            Notification::make()->title('No se pudo registrar')->body($exception->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return EmployeeExitResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
