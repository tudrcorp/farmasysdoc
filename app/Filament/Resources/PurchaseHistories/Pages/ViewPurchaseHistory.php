<?php

namespace App\Filament\Resources\PurchaseHistories\Pages;

use App\Filament\Resources\PurchaseHistories\PurchaseHistoryResource;
use App\Models\PurchaseHistory;
use App\Services\Audit\AuditLogger;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseHistory extends ViewRecord
{
    protected static string $resource = PurchaseHistoryResource::class;

    protected static ?string $title = 'Detalle del histórico de compra';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $model = $this->getRecord();
        if ($model instanceof PurchaseHistory) {
            AuditLogger::forModel(
                $model,
                'purchase_history_viewed',
                [
                    'entry_type' => $model->entry_type,
                ],
            );
        }
    }
}
