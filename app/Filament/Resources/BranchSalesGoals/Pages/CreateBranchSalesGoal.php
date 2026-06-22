<?php

namespace App\Filament\Resources\BranchSalesGoals\Pages;

use App\Filament\Resources\BranchSalesGoals\BranchSalesGoalResource;
use App\Filament\Resources\BranchSalesGoals\Schemas\BranchSalesGoalForm;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class CreateBranchSalesGoal extends CreateRecord
{
    protected static string $resource = BranchSalesGoalResource::class;

    protected static ?string $title = 'Nueva meta de ventas';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = BranchSalesGoalForm::normalizeScopeFields($data);
        BranchSalesGoalForm::assertUniqueScope($data);

        $user = Auth::user();
        if ($user instanceof User) {
            $actor = filled($user->email) ? (string) $user->email : (string) $user->getKey();
            $data['created_by'] = $actor;
            $data['updated_by'] = $actor;
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
        ];
    }
}
