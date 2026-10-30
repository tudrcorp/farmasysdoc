<?php

namespace App\Http\Controllers\Hr;

use App\Filament\Resources\Hr\Training\Enrollments\TrainingEnrollmentResource;
use App\Http\Controllers\Controller;
use App\Models\TrainingEnrollment;
use App\Models\User;
use App\Services\Training\TrainingCertificatePdfFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TrainingCertificateController extends Controller
{
    public function __invoke(Request $request, TrainingEnrollment $enrollment, TrainingCertificatePdfFactory $pdfFactory): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canAccessTrainingRecords(), 403);
        abort_unless(TrainingEnrollmentResource::getEloquentQuery()->whereKey($enrollment->id)->exists(), 403);
        abort_unless($enrollment->canDownloadCertificate(), 404);

        return $pdfFactory->download($enrollment);
    }
}
