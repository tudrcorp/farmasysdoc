<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\TrainingEnrollment;
use App\Services\Hr\EmployeePortalAccess;
use App\Services\Training\TrainingCertificatePdfFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EmployeePortalTrainingCertificateController extends Controller
{
    public function __invoke(
        TrainingEnrollment $enrollment,
        EmployeePortalAccess $access,
        TrainingCertificatePdfFactory $pdfFactory,
    ): StreamedResponse {
        $employee = $access->employee();
        abort_unless($employee !== null && (int) $enrollment->employee_id === (int) $employee->id, 403);
        abort_unless($enrollment->canDownloadCertificate(), 403);

        return $pdfFactory->download($enrollment);
    }
}
