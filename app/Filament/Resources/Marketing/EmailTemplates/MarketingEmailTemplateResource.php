<?php

namespace App\Filament\Resources\Marketing\EmailTemplates;

use App\Filament\Resources\Marketing\Concerns\ChecksMarketingAccess;
use App\Filament\Resources\Marketing\EmailTemplates\Pages\CreateMarketingEmailTemplate;
use App\Filament\Resources\Marketing\EmailTemplates\Pages\EditMarketingEmailTemplate;
use App\Filament\Resources\Marketing\EmailTemplates\Pages\ListMarketingEmailTemplates;
use App\Filament\Resources\Marketing\EmailTemplates\Schemas\MarketingEmailTemplateForm;
use App\Filament\Resources\Marketing\EmailTemplates\Tables\MarketingEmailTemplatesTable;
use App\Models\MarketingEmailTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MarketingEmailTemplateResource extends Resource
{
    use ChecksMarketingAccess;

    protected static ?string $model = MarketingEmailTemplate::class;

    protected static ?string $navigationLabel = 'Plantillas de correo';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 13;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::EnvelopeOpen;

    public static function form(Schema $schema): Schema
    {
        return MarketingEmailTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingEmailTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingEmailTemplates::route('/'),
            'create' => CreateMarketingEmailTemplate::route('/create'),
            'edit' => EditMarketingEmailTemplate::route('/{record}/edit'),
        ];
    }
}
