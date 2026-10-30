<?php

namespace App\Filament\Resources\Hr\Recruitment\Requests;

use App\Enums\RecruitmentRequestStatus;
use App\Filament\Clusters\Recruitment\RecruitmentCluster;
use App\Filament\Resources\Hr\Recruitment\Requests\Pages\CreateRecruitmentRequest;
use App\Filament\Resources\Hr\Recruitment\Requests\Pages\EditRecruitmentRequest;
use App\Filament\Resources\Hr\Recruitment\Requests\Pages\ListRecruitmentRequests;
use App\Filament\Resources\Hr\Recruitment\Requests\Pages\ViewRecruitmentRequest;
use App\Filament\Resources\Hr\Recruitment\Requests\Schemas\RecruitmentRequestForm;
use App\Filament\Resources\Hr\Recruitment\Requests\Schemas\RecruitmentRequestInfolist;
use App\Filament\Resources\Hr\Recruitment\Requests\Tables\RecruitmentRequestsTable;
use App\Models\RecruitmentRequest;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RecruitmentRequestResource extends Resource
{
    protected static ?string $model = RecruitmentRequest::class;

    protected static ?string $cluster = RecruitmentCluster::class;

    protected static ?string $slug = 'solicitudes-personal';

    protected static ?string $navigationLabel = 'Solicitudes de personal';

    protected static ?string $modelLabel = 'solicitud de personal';

    protected static ?string $pluralModelLabel = 'solicitudes de personal';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    public static function form(Schema $schema): Schema
    {
        return RecruitmentRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentRequestsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return BranchAuthScope::apply(parent::getEloquentQuery()->with(['branch', 'requester']));
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getEloquentQuery()->where('status', RecruitmentRequestStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Solicitudes por aprobar';
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
            && $user->canAccessRecruitmentRequests();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        $user = static::currentUser();

        return $record instanceof RecruitmentRequest
            && $record->isPending()
            && $user !== null
            && ($user->isAdministrator() || $record->requested_by === $user->id);
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
            'index' => ListRecruitmentRequests::route('/'),
            'create' => CreateRecruitmentRequest::route('/create'),
            'view' => ViewRecruitmentRequest::route('/{record}'),
            'edit' => EditRecruitmentRequest::route('/{record}/edit'),
        ];
    }

    public static function currentUser(): ?User
    {
        $user = request()->user() ?? Auth::user();

        return $user instanceof User ? $user : null;
    }
}
