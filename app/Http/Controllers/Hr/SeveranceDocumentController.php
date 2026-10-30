<?php

namespace App\Http\Controllers\Hr;

use App\Filament\Resources\Hr\Severance\Exits\EmployeeExitResource;
use App\Filament\Resources\Hr\Severance\Settlements\EmployeeSettlementResource;
use App\Http\Controllers\Controller;
use App\Models\EmployeeExit;
use App\Models\EmployeeSettlement;
use App\Services\Severance\EmployeeSettlementPdfFactory;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SeveranceDocumentController extends Controller
{
    public function settlement(EmployeeSettlement $settlement, EmployeeSettlementPdfFactory $pdfFactory): StreamedResponse
    {
        abort_unless(EmployeeSettlementResource::canViewAny(), 403);

        return $pdfFactory->download($settlement);
    }

    public function exitDocument(EmployeeExit $exit): StreamedResponse
    {
        abort_unless(EmployeeExitResource::canViewAny(), 403);
        abort_unless(EmployeeExitResource::getEloquentQuery()->whereKey($exit->id)->exists(), 403);

        $path = (string) $exit->document_path;
        $disk = Storage::disk(EmployeeExit::DOCUMENT_DISK);
        abort_unless($path !== '' && ! str_contains($path, '..') && $disk->exists($path), 404);

        return $disk->response($path);
    }
}
