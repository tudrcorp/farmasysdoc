<?php

namespace App\Services\Orders;

use App\Models\OrderAcceptance;
use App\Support\Fiscal\CompanyFiscalAddress;
use App\Support\Fiscal\VenezuelanRifFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OrderAcceptanceNotePdfFactory
{
    /**
     * @return array<string, mixed>
     */
    public function viewData(OrderAcceptance $acceptance): array
    {
        $acceptance->loadMissing(['items', 'order.partnerCompany', 'partnerCompany']);

        return [
            'acceptance' => $acceptance,
            'company_name' => (string) config('fiscal.retention_agent.name'),
            'company_rif' => VenezuelanRifFormatter::format((string) config('fiscal.retention_agent.rif')),
            'company_address' => CompanyFiscalAddress::line(),
            'pdf_logo_data_uri' => $this->logoDataUri(),
        ];
    }

    public function output(OrderAcceptance $acceptance): string
    {
        return Pdf::loadView('pdf.order-acceptance-note', $this->viewData($acceptance))
            ->setPaper('letter', 'portrait')
            ->output();
    }

    public function download(OrderAcceptance $acceptance): Response
    {
        return response($this->output($acceptance), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$acceptance->fileName().'"',
        ]);
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('images/logos/farmadoc-dark.png');

        if (! is_readable($path)) {
            $path = public_path('images/logos/farmadoc-ligth.png');
        }

        if (! is_readable($path)) {
            return null;
        }

        $image = @imagecreatefrompng($path);

        if ($image === false) {
            return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
        }

        $cropped = $this->cropTransparentPadding($image);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);

        ob_start();
        imagepng($cropped);
        $bytes = ob_get_clean();

        if ($cropped !== $image) {
            imagedestroy($cropped);
        }

        imagedestroy($image);

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($bytes);
    }

    private function cropTransparentPadding(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $minX = $width;
        $minY = $height;
        $maxX = 0;
        $maxY = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;

                if ($alpha >= 20) {
                    continue;
                }

                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            return $image;
        }

        $cropped = imagecrop($image, [
            'x' => $minX,
            'y' => $minY,
            'width' => $maxX - $minX + 1,
            'height' => $maxY - $minY + 1,
        ]);

        return $cropped instanceof \GdImage ? $cropped : $image;
    }

    public function stream(OrderAcceptance $acceptance): StreamedResponse
    {
        $output = $this->output($acceptance);

        return response()->streamDownload(
            function () use ($output): void {
                echo $output;
            },
            $acceptance->fileName(),
            ['Content-Type' => 'application/pdf'],
        );
    }
}
