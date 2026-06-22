<?php

namespace App\Filament\Resources\BranchSalesGoals\Pages;

use App\Filament\Resources\BranchSalesGoals\BranchSalesGoalResource;
use App\Filament\Resources\BranchSalesGoals\Schemas\BranchSalesGoalForm;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditBranchSalesGoal extends EditRecord
{
    protected static string $resource = BranchSalesGoalResource::class;

    protected static ?string $title = 'Editar meta de ventas';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = BranchSalesGoalForm::normalizeScopeFields($data);
        BranchSalesGoalForm::assertUniqueScope($data, $this->getRecord());

        $user = Auth::user();
        if ($user instanceof User) {
            $data['updated_by'] = filled($user->email) ? (string) $user->email : (string) $user->getKey();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--gray',
                ])
                ->url(BranchSalesGoalResource::getUrl()),
            DeleteAction::make(),
        ];
    }
}
