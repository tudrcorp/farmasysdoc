<?php

namespace App\Filament\Tables\Columns\Summarizers;

use App\Filament\Tables\Columns\Summarizers\Concerns\RendersCopyableSummary;
use Filament\Tables\Columns\Summarizers\Sum;

class CopyableSum extends Sum
{
    use RendersCopyableSummary;
}
