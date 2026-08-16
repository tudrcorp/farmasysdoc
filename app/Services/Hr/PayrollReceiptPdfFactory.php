<?php

namespace App\Services\Hr;

use App\Models\HrPayrollReceipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PayrollReceiptPdfFactory
{
    /**
     * @return array{
     *     receipt: HrPayrollReceipt,
     *     pdf_logo_data_uri: ?string,
     *     signature_data_uri: ?string,
     *     fingerprint_data_uri: ?string
     * }
     */
    public function viewData(HrPayrollReceipt $receipt): array
    {
        $receipt->loadMissing('employee');

        return [
            'receipt' => $receipt,
            'pdf_logo_data_uri' => $this->publicImageDataUri('images/logos/farmadoc-ligth.png'),
            'signature_data_uri' => $this->storedImageDataUri($receipt->employee?->signature_path),
            'fingerprint_data_uri' => $this->storedImageDataUri($receipt->employee?->fingerprint_path),
        ];
    }

    public function output(HrPayrollReceipt $receipt): string
    {
        return Pdf::loadView('pdf.payroll-receipt', $this->viewData($receipt))
            ->setPaper('letter', 'portrait')
            ->output();
    }

    public function download(HrPayrollReceipt $receipt): StreamedResponse
    {
        $filename = $receipt->fileName();
        $pdf = Pdf::loadView('pdf.payroll-receipt', $this->viewData($receipt))
            ->setPaper('letter', 'portrait');

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf->output();
            },
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function stream(HrPayrollReceipt $receipt): StreamedResponse
    {
        return $this->download($receipt);
    }

    private function publicImageDataUri(string $relativePath): ?string
    {
        $path = public_path($relativePath);
        if (! is_readable($path)) {
            return null;
        }

        return $this->dataUriFromBytes((string) file_get_contents($path), $path);
    }

    private function storedImageDataUri(?string $path): ?string
    {
        if (! filled($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $bytes = Storage::disk('public')->get($path);
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return $this->dataUriFromBytes($bytes, $path);
    }

    private function dataUriFromBytes(string $bytes, string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
