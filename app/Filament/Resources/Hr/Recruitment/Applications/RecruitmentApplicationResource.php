<?php

namespace App\Filament\Resources\Hr\Recruitment\Applications;

use App\Filament\Clusters\Recruitment\RecruitmentCluster;
use App\Filament\Resources\Hr\Recruitment\Applications\Pages\ManageRecruitmentSelection;
use App\Filament\Resources\Hr\Recruitment\Applications\Pages\ViewRecruitmentApplication;
use App\Filament\Resources\Hr\Recruitment\Applications\Schemas\RecruitmentApplicationInfolist;
use App\Models\RecruitmentApplication;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RecruitmentApplicationResource extends Resource
{
    protected static ?string $model = RecruitmentApplication::class;

    protected static ?string $cluster = RecruitmentCluster::class;

    protected static ?string $slug = 'seleccion';

    protected static ?string $navigationLabel = 'Selección';

    protected static ?string $modelLabel = 'proceso de selección';

    protected static ?string $pluralModelLabel = 'procesos de selección';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ViewColumns;

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof RecruitmentApplication ? $record->candidate->fullName() : parent::getRecordTitle($record);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentApplicationInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['candidate', 'recruitmentRequest.branch'])
            ->whereHas('recruitmentRequest', fn (Builder $query): Builder => BranchAuthScope::apply($query));
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
            && $user->canAccessRecruitmentSelection();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
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
            'index' => ManageRecruitmentSelection::route('/'),
            'view' => ViewRecruitmentApplication::route('/{record}'),
        ];
    }

    public static function currentUser(): ?User
    {
        $user = request()->user() ?? Auth::user();

        return $user instanceof User ? $user : null;
    }
}
