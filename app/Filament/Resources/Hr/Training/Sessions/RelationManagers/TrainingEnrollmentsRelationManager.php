<?php

namespace App\Filament\Resources\Hr\Training\Sessions\RelationManagers;

use App\Filament\Resources\Hr\Training\Enrollments\Tables\TrainingEnrollmentsTable;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrainingEnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Participantes';

    protected static ?string $modelLabel = 'participante';

    protected static ?string $pluralModelLabel = 'participantes';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();

        return TrainingEnrollmentsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $user instanceof User && ! $user->isAdministrator()
                ? $query->whereHas('employee', fn (Builder $employees): Builder => BranchAuthScope::apply($employees))
                : $query);
    }
}
