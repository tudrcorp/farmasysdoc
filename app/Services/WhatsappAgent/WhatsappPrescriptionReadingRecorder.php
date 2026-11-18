<?php

namespace App\Services\WhatsappAgent;

use App\Enums\PrescriptionLegibility;
use App\Enums\WhatsappImageKind;
use App\Models\PrescriptionReading;
use App\Models\Product;
use App\Models\WhatsappAgentSetting;
use App\Models\WhatsappConversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Guarda la lectura del récipe que hizo el modelo de visión en n8n y aplica la regla de escalamiento
 * aquí (no en la IA): ilegible, dudosa, sin medicamentos, confianza baja o un controlado → analista.
 */
final class WhatsappPrescriptionReadingRecorder
{
    public function __construct(private readonly WhatsappConversationEscalator $escalator) {}

    /**
     * @param  array{
     *     whatsapp_message_id?: int|null,
     *     is_prescription?: bool,
     *     legibility: string,
     *     overall_confidence?: float|null,
     *     doctor_name?: string|null,
     *     prescription_date?: string|null,
     *     issues?: list<string>|null,
     *     items?: list<array<string, mixed>>,
     *     raw_response?: array<string, mixed>|null,
     * }  $data
     */
    public function record(WhatsappConversation $conversation, array $data): PrescriptionReading
    {
        $threshold = (float) WhatsappAgentSetting::current()->prescription_confidence_threshold;
        $legibility = PrescriptionLegibility::from($data['legibility']);
        $items = array_values($data['items'] ?? []);

        $reading = DB::transaction(function () use ($conversation, $data, $legibility, $items): PrescriptionReading {
            $kind = WhatsappImageKind::tryFrom((string) ($data['image_kind'] ?? ''))
                ?? (($data['is_prescription'] ?? true) ? WhatsappImageKind::Recipe : WhatsappImageKind::Other);

            $reading = PrescriptionReading::query()->create([
                'whatsapp_conversation_id' => $conversation->getKey(),
                'whatsapp_message_id' => $data['whatsapp_message_id'] ?? null,
                'is_prescription' => $kind === WhatsappImageKind::Recipe,
                'image_kind' => $kind,
                'description' => $data['description'] ?? null,
                'payment_data' => $data['payment_data'] ?? null,
                'model' => $data['model'] ?? null,
                'legibility' => $legibility,
                'overall_confidence' => $data['overall_confidence'] ?? null,
                'doctor_name' => $data['doctor_name'] ?? null,
                'prescription_date' => $data['prescription_date'] ?? null,
                'issues' => $data['issues'] ?? null,
                'raw_response' => $data['raw_response'] ?? null,
            ]);

            foreach ($items as $item) {
                $product = $this->matchProduct((string) ($item['medication_name'] ?? ''), $item['concentration'] ?? null);

                $reading->items()->create([
                    'medication_name' => mb_substr((string) ($item['medication_name'] ?? ''), 0, 255),
                    'concentration' => $item['concentration'] ?? null,
                    'presentation' => $item['presentation'] ?? null,
                    'quantity_text' => $item['quantity_text'] ?? null,
                    'dosage_instructions' => $item['dosage_instructions'] ?? null,
                    'confidence' => $item['confidence'] ?? null,
                    'product_id' => $product?->getKey(),
                    'is_controlled_substance' => (bool) ($item['is_controlled_substance'] ?? false) || (bool) $product?->is_controlled_substance,
                ]);
            }

            return $reading->load('items');
        });

        $reasons = $this->escalationReasons($reading, $threshold);

        $reading->forceFill([
            'requires_analyst' => $reasons !== [],
            'has_controlled_substance' => $reading->items->contains('is_controlled_substance', true),
        ])->save();

        if ($reasons !== []) {
            $this->escalator->escalate($conversation->refresh(), Str::ucfirst(rtrim(implode('; ', $reasons), '.')).'.');
        }

        return $reading;
    }

    /**
     * Cuándo pasa la imagen al equipo. Un récipe dudoso NO se escala: el asistente le pide al cliente
     * que confirme los medicamentos que no se leyeron bien, y el analista lo valida al crear el pedido.
     *
     * @return list<string>
     */
    public function escalationReasons(PrescriptionReading $reading, float $threshold): array
    {
        if ($reading->image_kind === WhatsappImageKind::PaymentProof) {
            $payment = $reading->payment_data ?? [];

            return ['comprobante de pago para verificar'.collect([
                $payment['amount'] ?? null,
                filled($payment['reference'] ?? null) ? 'ref. '.$payment['reference'] : null,
                $payment['bank'] ?? null,
            ])->filter()->map(fn ($part): string => ' · '.$part)->implode('')];
        }

        if ($reading->image_kind !== WhatsappImageKind::Recipe) {
            return [];
        }

        $reasons = [];

        if ($reading->legibility === PrescriptionLegibility::Illegible) {
            $reasons[] = 'la foto del récipe es ilegible';
        }

        if ($reading->items->isEmpty()) {
            $reasons[] = 'no se identificaron medicamentos en el récipe';
        }

        if ($reading->items->contains('is_controlled_substance', true)) {
            $reasons[] = 'el récipe incluye un medicamento controlado';
        }

        return array_values(array_unique($reasons));
    }

    private function matchProduct(string $name, mixed $concentration): ?Product
    {
        $name = trim($name);

        if (mb_strlen($name) < 3) {
            return null;
        }

        $first = (string) (preg_split('/\s+/u', $name)[0] ?? $name);
        $like = '%'.addcslashes($first, '%_\\').'%';

        return Product::query()
            ->where('is_active', true)
            ->where(fn (Builder $query): Builder => $query->where('name', 'like', $like)->orWhere('active_ingredient', 'like', $like))
            ->when(is_string($concentration) && trim($concentration) !== '', function (Builder $query) use ($concentration): void {
                $digits = (string) preg_replace('/\D+/', '', (string) $concentration);

                if ($digits !== '') {
                    $query->orderByRaw('CASE WHEN concentration LIKE ? OR name LIKE ? THEN 0 ELSE 1 END', ['%'.$digits.'%', '%'.$digits.'%']);
                }
            })
            ->orderBy('name')
            ->first();
    }
}
