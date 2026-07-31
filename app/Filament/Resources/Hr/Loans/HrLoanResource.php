<?php

namespace App\Filament\Resources\Hr\Loans;

use App\Filament\Resources\Hr\Concerns\ChecksHrLoanAccess;
use App\Filament\Resources\Hr\Loans\Pages\CreateHrLoan;
use App\Filament\Resources\Hr\Loans\Pages\EditHrLoan;
use App\Filament\Resources\Hr\Loans\Pages\ListHrLoans;
use App\Filament\Resources\Hr\Loans\Pages\ViewHrLoan;
use App\Filament\Resources\Hr\Loans\Schemas\HrLoanForm;
use App\Filament\Resources\Hr\Loans\Schemas\HrLoanInfolist;
use App\Filament\Resources\Hr\Loans\Tables\HrLoansTable;
use App\Models\HrLoan;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class HrLoanResource extends Resource
{
    use ChecksHrLoanAccess;

    protected static ?string $model = HrLoan::class;

    protected static ?string $navigationLabel = 'Préstamos';

    protected static ?string $modelLabel = 'préstamo';

    protected static ?string $pluralModelLabel = 'préstamos';

    protected static string|UnitEnum|null $navigationGroup = 'hr';

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    public static function form(Schema $schema): Schema
    {
        return HrLoanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return HrLoanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HrLoansTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return BranchAuthScope::apply(parent::getEloquentQuery());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHrLoans::route('/'),
            'create' => CreateHrLoan::route('/create'),
            'view' => ViewHrLoan::route('/{record}'),
            'edit' => EditHrLoan::route('/{record}/edit'),
        ];
    }

    public static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
