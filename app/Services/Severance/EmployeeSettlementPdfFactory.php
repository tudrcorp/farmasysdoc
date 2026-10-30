<?php

namespace App\Services\Severance;

use App\Enums\EmployeeSettlementLineType;
use App\Enums\EmployeeSettlementStatus;
use App\Models\EmployeeSettlement;
use App\Services\Hr\EmploymentCertificatePdfFactory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Planilla de liquidación y finiquito, generada al descargar desde los datos ya guardados.
 */
final class EmployeeSettlementPdfFactory
{
    public function __construct(private EmploymentCertificatePdfFactory $assets) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(EmployeeSettlement $settlement): array
    {
        $settlement->loadMissing(['employeeExit.employee.branch', 'lines']);
        $employee = $settlement->employeeExit->employee;

        return [
            'settlement' => $settlement,
            'employee' => $employee,
            'exit' => $settlement->employeeExit,
            'assignments' => $settlement->lines->where('type', EmployeeSettlementLineType::Assignment),
            'deductions' => $settlement->lines->where('type', EmployeeSettlementLineType::Deduction),
            'is_final' => in_array($settlement->status, [EmployeeSettlementStatus::Approved, EmployeeSettlementStatus::Paid], true),
            'logo_data_uri' => $this->assets->publicImageDataUri('images/logos/farmadoc-ligth.png'),
            'reference' => 'LIQ-'.str_pad((string) $settlement->id, 6, '0', STR_PAD_LEFT),
        ];
    }

    public function download(EmployeeSettlement $settlement): StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.employee-settlement', $this->viewData($settlement))
            ->setPaper('letter', 'portrait')
            ->setOption('enable_font_subsetting', true);

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf->output();
            },
            'liquidacion-'.Str::slug($settlement->employeeExit->employee->fullName()).'-'.$settlement->id.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
