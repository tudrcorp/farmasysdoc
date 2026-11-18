<?php

namespace App\Http\Controllers\Api\WhatsappAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsappAgent\StorePrescriptionReadingRequest;
use App\Models\PrescriptionReadingItem;
use App\Models\WhatsappConversation;
use App\Services\WhatsappAgent\WhatsappPrescriptionReadingRecorder;
use Illuminate\Http\JsonResponse;

class WhatsappAgentPrescriptionController extends Controller
{
    public function store(StorePrescriptionReadingRequest $request, WhatsappConversation $conversation, WhatsappPrescriptionReadingRecorder $recorder): JsonResponse
    {
        $reading = $recorder->record($conversation->loadMissing('contact'), $request->validated());

        return response()->json([
            'reading_id' => (int) $reading->getKey(),
            'requires_analyst' => $reading->requires_analyst,
            'has_controlled_substance' => $reading->has_controlled_substance,
            'items' => $reading->items->map(fn (PrescriptionReadingItem $item): array => [
                'medication' => $item->medication_name,
                'concentration' => $item->concentration,
                'confidence' => $item->confidence !== null ? (float) $item->confidence : null,
                'matched_product_id' => $item->product_id,
            ])->all(),
            'instruction' => $reading->requires_analyst
                ? 'El récipe pasó a una farmacéutica del equipo. Díselo al cliente con calidez y no adivines medicamentos.'
                : 'Confirma con el cliente lo leído y busca cada medicamento con buscar_producto.',
        ]);
    }
}
