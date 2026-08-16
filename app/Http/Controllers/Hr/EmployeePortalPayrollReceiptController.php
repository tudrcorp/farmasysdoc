<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrPayrollReceipt;
use App\Services\Hr\EmployeePortalAccess;
use App\Services\Hr\PayrollReceiptAvailability;
use App\Services\Hr\PayrollReceiptPdfFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EmployeePortalPayrollReceiptController extends Controller
{
    public function __invoke(
        HrPayrollReceipt $receipt,
        EmployeePortalAccess $access,
        PayrollReceiptAvailability $availability,
        PayrollReceiptPdfFactory $pdfFactory,
    ): StreamedResponse {
        $employee = $access->employee();
        abort_unless($employee !== null && (int) $receipt->employee_id === (int) $employee->id, 403);
        abort_unless($availability->isAvailable((int) $receipt->year, (int) $receipt->month), 403);

        return $pdfFactory->download($receipt);
    }
}
