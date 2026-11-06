<?php

namespace App\Filament\Resources\PartnerAgreements;

use App\Filament\Resources\Concerns\ChecksConfigurationAccess;
use App\Filament\Resources\PartnerAgreements\Pages\CreatePartnerCompanyAgreement;
use App\Filament\Resources\PartnerAgreements\Pages\EditPartnerCompanyAgreement;
use App\Filament\Resources\PartnerAgreements\Pages\ListPartnerCompanyAgreements;
use App\Filament\Resources\PartnerAgreements\Pages\ViewPartnerCompanyAgreement;
use App\Filament\Resources\PartnerAgreements\Schemas\PartnerCompanyAgreementForm;
use App\Filament\Resources\PartnerAgreements\Schemas\PartnerCompanyAgreementInfolist;
use App\Filament\Resources\PartnerAgreements\Tables\PartnerCompanyAgreementsTable;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PartnerCompanyAgreementResource extends Resource
{
    use ChecksConfigurationAccess;

    protected static ?string $model = PartnerCompanyAgreement::class;

    protected static ?string $slug = 'partner-agreements';

    protected static ?string $modelLabel = 'convenio';

    protected static ?string $pluralModelLabel = 'convenios';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $navigationLabel = 'Convenios y renovaciones';

    protected static ?int $navigationSort = 2;

    protected static string|UnitEnum|null $navigationGroup = 'commercial_allies';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentCheck;

    /**
     * @return Builder<PartnerCompanyAgreement>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn(
            'partner_company_id',
            BranchAuthScope::applyToPartnerCompaniesQuery(PartnerCompany::query())->select('partner_companies.id'),
        );
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->expiringWithin(30)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Convenios vigentes que vencen en los próximos 30 días';
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof PartnerCompanyAgreement && $record->status->isEditable() && static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PartnerCompanyAgreementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PartnerCompanyAgreementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartnerCompanyAgreementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerCompanyAgreements::route('/'),
            'create' => CreatePartnerCompanyAgreement::route('/create'),
            'view' => ViewPartnerCompanyAgreement::route('/{record}'),
            'edit' => EditPartnerCompanyAgreement::route('/{record}/edit'),
        ];
    }
}
