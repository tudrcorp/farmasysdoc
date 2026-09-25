<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Reports\ExecutiveKpiBoard;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class ExecutiveKpisPage extends Page
{
    protected static ?string $title = 'KPIs directivos';

    protected static ?string $navigationLabel = 'KPIs directivos';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = -1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::PresentationChartLine;

    protected static ?string $slug = 'kpis-directivos';

    protected string $view = 'filament.pages.executive-kpis';

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'board' => app(ExecutiveKpiBoard::class)->snapshot(),
        ];
    }
}
