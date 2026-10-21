<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Support\Notifications\UltramsgWhatsAppClient;
use App\Support\Notifications\WhatsAppLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class FinancialReportDeliverer
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly FinancialReportExcelExporter $excelExporter,
        private readonly FinancialReportSummaryMessageBuilder $messageBuilder,
        private readonly UltramsgWhatsAppClient $whatsAppClient,
    ) {}

    /**
     * @param  array<string, mixed>  $report
     * @return array{whatsapp: bool, whatsapp_error: ?string, token: string, filename: string}
     */
    public function deliver(User $user, array $report): array
    {
        if ($report === []) {
            abort(422, 'Genere el reporte antes de exportar.');
        }

        $excelBytes = $this->excelExporter->generate($report);
        $filename = $this->excelExporter->filename();
        $whatsappResult = $this->sendWhatsAppWithBytes($user, $report, $excelBytes, $filename);

        $token = Str::uuid()->toString();
        Cache::put($this->cacheKey($user, $token), $excelBytes, self::CACHE_TTL_SECONDS);

        return [
            'whatsapp' => $whatsappResult['ok'],
            'whatsapp_error' => $whatsappResult['error'],
            'token' => $token,
            'filename' => $filename,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function downloadResponse(User $user, array $report, ?string $token = null): Response
    {
        if ($report === []) {
            abort(422, 'Genere el reporte antes de exportar.');
        }

        $filename = $this->excelExporter->filename();

        if ($token !== null) {
            $cached = Cache::pull($this->cacheKey($user, $token));
            if (is_string($cached) && $cached !== '') {
                return $this->binaryResponse($cached, $filename);
            }
        }

        return $this->binaryResponse($this->excelExporter->generate($report), $filename);
    }

    private function binaryResponse(string $excelBytes, string $filename): Response
    {
        return response($excelBytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{ok: bool, error: ?string}
     */
    private function sendWhatsAppWithBytes(
        User $user,
        array $report,
        string $excelBytes,
        string $filename,
    ): array {
        if (! $this->whatsAppClient->isEnabled()) {
            return ['ok' => false, 'error' => 'WhatsApp no está configurado en el servidor.'];
        }

        $phone = WhatsAppLink::normalizePhoneDigits(
            filled($user->whatsapp_phone) ? $user->whatsapp_phone : $user->delivery_mobile_phone,
        );

        if ($phone === null) {
            return ['ok' => false, 'error' => 'Configure su teléfono WhatsApp en su perfil de usuario.'];
        }

        $message = $this->messageBuilder->build($report);
        $periodLabel = (string) ($report['period']['label'] ?? '');
        $documentCaption = 'Reporte financiero · '.$periodLabel;

        try {
            $sentText = $this->whatsAppClient->sendTextMessage($phone, $message);

            if (! $sentText) {
                return ['ok' => false, 'error' => 'No se pudo enviar el resumen por WhatsApp.'];
            }

            $sentDocument = $this->whatsAppClient->sendDocumentMessage(
                $phone,
                base64_encode($excelBytes),
                $filename,
                $documentCaption,
            );

            if (! $sentDocument) {
                return ['ok' => false, 'error' => 'Se envió el resumen, pero no el archivo Excel.'];
            }

            return ['ok' => true, 'error' => null];
        } catch (Throwable $exception) {
            Log::warning('Reporte financiero: error al enviar WhatsApp', [
                'user_id' => $user->id,
                'phone' => $phone,
                'error' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'Error al enviar por WhatsApp: '.$exception->getMessage()];
        }
    }

    private function cacheKey(User $user, string $token): string
    {
        return 'financial-report-excel:'.$user->id.':'.$token;
    }
}
