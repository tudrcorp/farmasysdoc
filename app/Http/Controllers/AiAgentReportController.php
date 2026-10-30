<?php

namespace App\Http\Controllers;

use App\Models\AiAgentReport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AiAgentReportController extends Controller
{
    public function __invoke(Request $request, AiAgentReport $report, string $format): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdministrator() && $report->user_id === $user->id, 403);

        return $format === 'pdf' ? $this->pdf($report) : $this->excel($report);
    }

    private function excel(AiAgentReport $report): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$report->columns, ...$report->rows], null, 'A1', true);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, Str::slug($report->title).'.xlsx');
    }

    private function pdf(AiAgentReport $report): Response
    {
        return Pdf::loadView('pdf.ai-agent-report', ['report' => $report])
            ->setPaper('letter', count($report->columns) > 5 ? 'landscape' : 'portrait')
            ->setOption('enable_font_subsetting', true)
            ->download(Str::slug($report->title).'.pdf');
    }
}
