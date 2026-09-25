<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\PurchaseLedgerBookReportBuilder;
use App\Support\Purchases\PurchaseBookSeniatRetentionTxtBuilder;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class PurchaseBookSeniatRetentionTxtController extends Controller
{
    public function __invoke(
        Request $request,
        PurchaseBookSeniatRetentionTxtBuilder $builder,
    ): Response {
        $this->authorizeAccess($request);

        $taxPeriod = (string) $request->query('tax_period', '');
        $half = (string) $request->query('half', PurchaseLedgerBookReportBuilder::HALF_MONTH);

        try {
            $contents = $builder->build($taxPeriod, $half);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        AuditLogger::record(
            event: 'purchase_book_seniat_retention_txt_downloaded',
            description: 'Retenciones: descarga del TXT de retención de IVA para el SENIAT.',
            properties: [
                'tax_period' => $taxPeriod,
                'half' => $half,
            ],
        );

        $filename = 'retencion-iva-'.str_replace('/', '-', $taxPeriod).'-'.$half.'.txt';

        return response($contents, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isAdministrator() || $user->canAccessFarmaadminMenuKey('purchase_books')) {
            return;
        }

        abort(403, 'No tienes permiso para Retenciones.');
    }
}
