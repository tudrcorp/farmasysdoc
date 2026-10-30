<?php

namespace App\Filament\Resources\Hr\Recruitment\Candidates;

use App\Filament\Clusters\Recruitment\RecruitmentCluster;
use App\Filament\Resources\Hr\Recruitment\Candidates\Pages\CreateRecruitmentCandidate;
use App\Filament\Resources\Hr\Recruitment\Candidates\Pages\EditRecruitmentCandidate;
use App\Filament\Resources\Hr\Recruitment\Candidates\Pages\ListRecruitmentCandidates;
use App\Filament\Resources\Hr\Recruitment\Candidates\Schemas\RecruitmentCandidateForm;
use App\Filament\Resources\Hr\Recruitment\Candidates\Tables\RecruitmentCandidatesTable;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RecruitmentCandidateResource extends Resource
{
    protected static ?string $model = RecruitmentCandidate::class;

    protected static ?string $cluster = RecruitmentCluster::class;

    protected static ?string $slug = 'candidatos';

    protected static ?string $navigationLabel = 'Candidatos';

    protected static ?string $modelLabel = 'candidato';

    protected static ?string $pluralModelLabel = 'candidatos';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof RecruitmentCandidate ? $record->fullName() : parent::getRecordTitle($record);
    }

    public static function form(Schema $schema): Schema
    {
        return RecruitmentCandidateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentCandidatesTable::configure($table);
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
            && ($user->isAdministrator() || $user->canAccessFarmaadminMenuKey('hr_recruitment_candidates'));
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny()
            && $record instanceof RecruitmentCandidate
            && $record->employee_id === null
            && ! $record->applications()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecruitmentCandidates::route('/'),
            'create' => CreateRecruitmentCandidate::route('/create'),
            'edit' => EditRecruitmentCandidate::route('/{record}/edit'),
        ];
    }

    public static function currentUser(): ?User
    {
        $user = request()->user() ?? Auth::user();

        return $user instanceof User ? $user : null;
    }
}
