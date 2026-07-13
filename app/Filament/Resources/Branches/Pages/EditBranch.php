<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditBranch extends EditRecord
{
    protected static string $resource = BranchResource::class;

    protected static ?string $title = 'Editar Informacion de la Sucursal';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['code']);

        $data['updated_by'] = auth()->user()?->email
            ?? auth()->user()?->name
            ?? 'sistema';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('profitMargins')
                ->label('Márgenes por categoría')
                ->icon(Heroicon::ChartBarSquare)
                ->color('warning')
                ->url(fn (): string => BranchResource::getUrl('profit-margins', ['record' => $this->getRecord()])),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
