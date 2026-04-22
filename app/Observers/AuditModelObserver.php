<?php

namespace App\Observers;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

final class AuditModelObserver
{
    public function created(Model $model): void
    {
        AuditLogger::forModel(
            $model,
            'created',
            ['attributes' => AuditLogger::sanitizeAttributes($model::class, $model->getAttributes())],
        );
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        if ($changes === []) {
            return;
        }

        AuditLogger::forModel(
            $model,
            'updated',
            [
                'changes' => AuditLogger::sanitizeAttributes($model::class, $changes),
                'original' => AuditLogger::sanitizeAttributes($model::class, array_intersect_key($model->getOriginal(), $changes)),
            ],
        );
    }

    public function deleted(Model $model): void
    {
        AuditLogger::forModel(
            $model,
            'deleted',
            ['attributes' => AuditLogger::sanitizeAttributes($model::class, $model->withoutRelations()->getAttributes())],
        );
    }
}
