<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShowExternalOrderRequest;
use App\Services\Orders\OrderAcceptanceNotePdfFactory;
use App\Support\Orders\ExternalPartnerOrderFinder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ExternalOrderAcceptanceNoteController extends Controller
{
    public function __invoke(
        ShowExternalOrderRequest $request,
        string $order,
        ExternalPartnerOrderFinder $finder,
        OrderAcceptanceNotePdfFactory $pdf,
    ): Response {
        $partner = $finder->partner((string) $request->validated('partner_company'));

        if ($partner === null) {
            return response()->json([
                'message' => 'La compañía aliada no existe o el código no coincide con un registro en el sistema.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $record = $finder->order($partner, $order);

        if ($record === null) {
            return response()->json([
                'message' => 'No se encontró un pedido de ese aliado con ese identificador.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $acceptance = $record->acceptance;

        if ($acceptance === null) {
            return response()->json([
                'message' => 'Este pedido todavía no tiene nota de entrega.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return $pdf->download($acceptance);
    }
}
