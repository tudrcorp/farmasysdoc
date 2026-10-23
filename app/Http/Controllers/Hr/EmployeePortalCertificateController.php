<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRequest;
use App\Services\Hr\EmployeePortalAccess;
use App\Services\Hr\EmploymentCertificatePdfFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EmployeePortalCertificateController extends Controller
{
    public function __invoke(
        EmployeeRequest $employeeRequest,
        EmployeePortalAccess $access,
        EmploymentCertificatePdfFactory $pdfFactory,
    ): StreamedResponse {
        $employee = $access->employee();
        abort_unless($employee !== null && (int) $employeeRequest->employee_id === (int) $employee->id, 403);
        abort_unless($employeeRequest->canDownloadCertificate(), 403);

        return $pdfFactory->download($employeeRequest);
    }
}
