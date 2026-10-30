<?php

namespace App\Filament\Resources\Hr\Training\Enrollments;

use App\Filament\Clusters\Training\TrainingCluster;
use App\Filament\Resources\Hr\Training\Enrollments\Pages\ListTrainingEnrollments;
use App\Filament\Resources\Hr\Training\Enrollments\Tables\TrainingEnrollmentsTable;
use App\Models\TrainingEnrollment;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TrainingEnrollmentResource extends Resource
{
    public const int EXPIRY_WARNING_DAYS = 30;

    protected static ?string $model = TrainingEnrollment::class;

    protected static ?string $cluster = TrainingCluster::class;

    protected static ?string $slug = 'asistencia';

    protected static ?string $navigationLabel = 'Asistencia y resultados';

    protected static ?string $modelLabel = 'participante';

    protected static ?string $pluralModelLabel = 'asistencia y resultados';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    public static function table(Table $table): Table
    {
        return TrainingEnrollmentsTable::configure($table, showSession: true);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['employee.branch', 'session.course'])
            ->whereHas('employee', fn (Builder $employees): Builder => BranchAuthScope::apply($employees));
    }

    public static function getNavigationBadge(): ?string
    {
        $expiring = static::getEloquentQuery()
            ->whereNotNull('expires_on')
            ->whereBetween('expires_on', [now()->toDateString(), now()->addDays(self::EXPIRY_WARNING_DAYS)->toDateString()])
            ->count();

        return $expiring > 0 ? (string) $expiring : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Certificaciones por vencer en 30 días';
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
            && $user->canAccessTrainingRecords();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrainingEnrollments::route('/'),
        ];
    }

    public static function currentUser(): ?User
    {
        $user = request()->user() ?? Auth::user();

        return $user instanceof User ? $user : null;
    }
}
