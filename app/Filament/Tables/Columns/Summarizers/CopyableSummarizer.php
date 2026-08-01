<?php

namespace App\Filament\Tables\Columns\Summarizers;

use App\Filament\Tables\Columns\Summarizers\Concerns\RendersCopyableSummary;
use Filament\Tables\Columns\Summarizers\Summarizer;

class CopyableSummarizer extends Summarizer
{
    use RendersCopyableSummary;
}
