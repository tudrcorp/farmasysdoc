<?php

namespace App\Filament\Resources\Hr\Training\Sessions;

use App\Enums\TrainingSessionStatus;
use App\Filament\Clusters\Training\TrainingCluster;
use App\Filament\Resources\Hr\Training\Sessions\Pages\CreateTrainingSession;
use App\Filament\Resources\Hr\Training\Sessions\Pages\EditTrainingSession;
use App\Filament\Resources\Hr\Training\Sessions\Pages\ListTrainingSessions;
use App\Filament\Resources\Hr\Training\Sessions\Pages\ViewTrainingSession;
use App\Filament\Resources\Hr\Training\Sessions\RelationManagers\TrainingEnrollmentsRelationManager;
use App\Filament\Resources\Hr\Training\Sessions\Schemas\TrainingSessionForm;
use App\Filament\Resources\Hr\Training\Sessions\Schemas\TrainingSessionInfolist;
use App\Filament\Resources\Hr\Training\Sessions\Tables\TrainingSessionsTable;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TrainingSessionResource extends Resource
{
    protected static ?string $model = TrainingSession::class;

    protected static ?string $cluster = TrainingCluster::class;

    protected static ?string $slug = 'programacion';

    protected static ?string $navigationLabel = 'Programación';

    protected static ?string $modelLabel = 'capacitación programada';

    protected static ?string $pluralModelLabel = 'capacitaciones programadas';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CalendarDays;

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof TrainingSession ? $record->optionLabel() : parent::getRecordTitle($record);
    }

    public static function form(Schema $schema): Schema
    {
        return TrainingSessionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TrainingSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrainingSessionsTable::configure($table);
    }

    /**
     * El gerente ve las de su sucursal y las generales donde participa su personal.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['course', 'branch', 'facilitatorEmployee']);
        $user = static::currentUser();

        if ($user === null || $user->isAdministrator()) {
            return $query;
        }

        return $query->where(fn (Builder $scoped): Builder => $scoped
            ->whereIn('branch_id', $user->restrictedBranchIdsForQueries())
            ->orWhereHas('enrollments.employee', fn (Builder $employees): Builder => BranchAuthScope::apply($employees)));
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        $user = static::currentUser();

        return $user !== null
            && ! FarmaadminDeliveryUserAccess::denies(static::class)
            && $user->canAccessTrainingSessions();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::currentUser()?->canManageTraining() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate()
            && $record instanceof TrainingSession
            && $record->status === TrainingSessionStatus::Scheduled;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            TrainingEnrollmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrainingSessions::route('/'),
            'create' => CreateTrainingSession::route('/create'),
            'view' => ViewTrainingSession::route('/{record}'),
            'edit' => EditTrainingSession::route('/{record}/edit'),
        ];
    }

    public static function currentUser(): ?User
    {
        $user = request()->user() ?? Auth::user();

        return $user instanceof User ? $user : null;
    }
}
