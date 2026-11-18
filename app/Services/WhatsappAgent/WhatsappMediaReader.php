<?php

namespace App\Services\WhatsappAgent;

use App\Enums\PrescriptionLegibility;
use App\Enums\WhatsappImageKind;
use App\Enums\WhatsappMessageAuthor;
use App\Enums\WhatsappMessageType;
use App\Models\PrescriptionReading;
use App\Models\WhatsappAgentSetting;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Entiende lo que manda el cliente antes de que responda el asistente:
 * clasifica cada foto (récipe, comprobante de pago, producto u otra), lee los récipes y transcribe las notas de voz.
 *
 * Los récipes que no quedan claros con el modelo rápido se releen con el modelo «pro» (mejor con letra a mano).
 */
final class WhatsappMediaReader
{
    private const IMAGE_PROMPT = <<<'PROMPT'
Eres una farmacéutica venezolana con 20 años de experiencia leyendo récipes médicos escritos a mano e impresos.
Analiza la imagen que un cliente envió por WhatsApp a una farmacia y responde SOLO con el JSON pedido.

1. image_kind — qué es la imagen:
   - "recipe": récipe o indicación médica (a mano o impreso; suele tener el récipe a un lado y las indicaciones al otro, sello y firma del médico), informe médico con tratamiento, o lista de medicamentos escrita por el médico.
   - "payment_proof": comprobante de pago (pago móvil, transferencia, Zelle, captura de la app del banco, punto de venta).
   - "product": foto de una caja, frasco, blíster o empaque de un producto.
   - "other": cualquier otra cosa.
2. description: en una frase corta y en español, qué muestra la imagen.
3. Si es "recipe":
   - Lee CADA medicamento: nombre (comercial o genérico tal como está escrito), concentración (ej. "50 mg", "500 mg/5 ml"), presentación (tabletas, cápsulas, jarabe, gotas, crema, ampollas…), cantidad indicada (ej. "1 caja", "30 tabletas", "#30") e indicaciones de uso (ej. "1 tableta cada 12 horas por 7 días").
   - Copia la concentración y la cantidad EXACTAMENTE como están escritas, aunque te parezcan raras. NUNCA las cambies por la dosis habitual del medicamento.
   - Si lo escrito no es una presentación habitual de ese medicamento (ej. "Ibuprofeno 10 mg"), deja lo escrito, pon confidence de 0.6 o menos y explícalo en unusual_note (ej. "Dice 10 mg; lo habitual es 200, 400 o 600 mg").
   - Une lo que aparece en la parte del récipe y en la de las indicaciones: es el mismo tratamiento.
   - Si reconoces el principio activo de un nombre comercial, ponlo en active_ingredient.
   - confidence (0 a 1) por medicamento: 0.9 o más solo si el nombre se lee sin duda.
   - is_controlled_substance: true para psicotrópicos o estupefacientes (ej. clonazepam, alprazolam, diazepam, lorazepam, zolpidem, tramadol, morfina, oxicodona, metilfenidato, fenobarbital).
   - legibility: "legible" si todos los medicamentos se leen con seguridad; "dudosa" si alguno no se lee bien; "ilegible" si la foto está muy borrosa, cortada, oscura o no se entiende.
   - doctor_name y prescription_date si aparecen.
   - NUNCA inventes ni completes un nombre que no se lee: baja la confianza y explica el problema en issues.
4. Si es "payment_proof": completa payment con monto (con la moneda tal como aparece, ej. "Bs 155.149,34" o "$20"), referencia, banco emisor, banco receptor, fecha y hora, teléfono o cuenta de destino y concepto.
5. Si es "product": pon el producto en items (nombre, concentración y presentación) con su confianza.
6. issues: problemas de la imagen o de la lectura, en español y en pocas palabras.
PROMPT;

    public function __construct(private readonly WhatsappPrescriptionReadingRecorder $recorder) {}

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    /**
     * Lee las fotos y transcribe las notas de voz que todavía no se procesaron.
     */
    public function readPending(WhatsappConversation $conversation): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $pending = $conversation->messages()
            ->where('author', WhatsappMessageAuthor::Customer->value)
            ->whereNotNull('media_path')
            ->where(function ($query): void {
                $query->where(fn ($image) => $image->where('type', WhatsappMessageType::Image->value)->doesntHave('prescriptionReading'))
                    ->orWhere(fn ($audio) => $audio->where('type', WhatsappMessageType::Audio->value)->whereNull('transcription'));
            })
            ->get();

        foreach ($pending as $message) {
            $message->type === WhatsappMessageType::Image
                ? $this->readImage($conversation, $message)
                : $this->transcribe($message);
        }
    }

    public function readImage(WhatsappConversation $conversation, WhatsappMessage $message): ?PrescriptionReading
    {
        $file = $this->fileParts($message);

        if ($file === null) {
            return null;
        }

        $fastModel = (string) config('services.whatsapp_agent.vision_model');
        $proModel = (string) config('services.whatsapp_agent.vision_pro_model');

        $reading = $this->askImage($fastModel, $file);
        $model = $fastModel;

        if ($proModel !== '' && $proModel !== $fastModel && $this->deservesSecondLook($reading)) {
            $second = $this->askImage($proModel, $file);

            if ($second !== null) {
                $reading = $second;
                $model = $proModel;
            }
        }

        $reading ??= [
            'image_kind' => WhatsappImageKind::Other->value,
            'description' => 'No se pudo leer la imagen.',
            'legibility' => PrescriptionLegibility::Illegible->value,
            'items' => [],
            'issues' => ['El lector de imágenes no respondió'],
        ];

        return $this->recorder->record($conversation, [
            ...$this->normalize($reading),
            'whatsapp_message_id' => (int) $message->getKey(),
            'raw_response' => $reading,
            'model' => $model,
        ]);
    }

    public function transcribe(WhatsappMessage $message): void
    {
        $file = $this->fileParts($message);

        if ($file === null) {
            return;
        }

        $text = $this->generate((string) config('services.whatsapp_agent.audio_model'), [
            ['text' => 'Transcribe literalmente, en español, esta nota de voz de un cliente de una farmacia en Venezuela. Devuelve solo el texto, sin comentarios. Si no se entiende nada, devuelve [inaudible].'],
            ['inline_data' => $file],
        ], ['temperature' => 0]);

        $message->forceFill(['transcription' => filled($text) ? trim((string) $text) : '[Nota de voz que no se pudo transcribir]'])->save();
    }

    /**
     * @param  array<string, mixed>|null  $reading
     */
    private function deservesSecondLook(?array $reading): bool
    {
        if ($reading === null) {
            return true;
        }

        if (($reading['image_kind'] ?? null) !== WhatsappImageKind::Recipe->value) {
            return false;
        }

        $threshold = (float) WhatsappAgentSetting::current()->prescription_confidence_threshold;
        $items = is_array($reading['items'] ?? null) ? $reading['items'] : [];

        return ($reading['legibility'] ?? null) !== PrescriptionLegibility::Legible->value
            || $items === []
            || collect($items)->contains(fn ($item): bool => (float) ($item['confidence'] ?? 0) < $threshold);
    }

    /**
     * @param  array{mime_type: string, data: string}  $file
     * @return array<string, mixed>|null
     */
    private function askImage(string $model, array $file): ?array
    {
        $text = $this->generate($model, [
            ['text' => self::IMAGE_PROMPT],
            ['inline_data' => $file],
        ], [
            'temperature' => 0.1,
            'responseMimeType' => 'application/json',
            'responseSchema' => self::imageSchema(),
        ]);

        $decoded = is_string($text) ? json_decode($text, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $reading
     * @return array<string, mixed>
     */
    private function normalize(array $reading): array
    {
        $kind = WhatsappImageKind::tryFrom((string) ($reading['image_kind'] ?? '')) ?? WhatsappImageKind::Other;
        $clamp = static fn (mixed $value): ?float => is_numeric($value) ? max(0.0, min(1.0, (float) $value)) : null;
        $legibility = PrescriptionLegibility::tryFrom((string) ($reading['legibility'] ?? ''))
            ?? ($kind === WhatsappImageKind::Recipe ? PrescriptionLegibility::Doubtful : PrescriptionLegibility::Legible);

        $items = collect(is_array($reading['items'] ?? null) ? $reading['items'] : [])
            ->filter(fn ($item): bool => is_array($item) && filled($item['medication_name'] ?? null))
            ->take(20)
            ->map(fn (array $item): array => [
                'medication_name' => trim((string) $item['medication_name'].(filled($item['active_ingredient'] ?? null) && ! str_contains(mb_strtolower((string) $item['medication_name']), mb_strtolower((string) $item['active_ingredient'])) ? ' ('.$item['active_ingredient'].')' : '')),
                'concentration' => $item['concentration'] ?? null,
                'presentation' => $item['presentation'] ?? null,
                'quantity_text' => $item['quantity_text'] ?? null,
                'dosage_instructions' => $item['dosage_instructions'] ?? null,
                'confidence' => $clamp($item['confidence'] ?? null),
                'is_controlled_substance' => (bool) ($item['is_controlled_substance'] ?? false),
            ])
            ->values()
            ->all();

        $payment = is_array($reading['payment'] ?? null) ? array_filter($reading['payment'], fn ($value): bool => filled($value)) : null;

        return [
            'image_kind' => $kind->value,
            'is_prescription' => $kind === WhatsappImageKind::Recipe,
            'description' => isset($reading['description']) ? mb_substr((string) $reading['description'], 0, 500) : null,
            'payment_data' => $kind === WhatsappImageKind::PaymentProof ? ($payment ?: null) : null,
            'legibility' => $legibility->value,
            'overall_confidence' => $clamp($reading['overall_confidence'] ?? null),
            'doctor_name' => filled($reading['doctor_name'] ?? null) ? mb_substr((string) $reading['doctor_name'], 0, 160) : null,
            'prescription_date' => filled($reading['prescription_date'] ?? null) ? mb_substr((string) $reading['prescription_date'], 0, 40) : null,
            'issues' => array_slice(array_values(array_filter([
                ...(array) ($reading['issues'] ?? []),
                ...collect(is_array($reading['items'] ?? null) ? $reading['items'] : [])
                    ->map(fn ($item): ?string => is_array($item) && filled($item['unusual_note'] ?? null) ? ($item['medication_name'] ?? 'Medicamento').': '.$item['unusual_note'] : null)
                    ->all(),
            ], fn ($issue): bool => is_string($issue) && $issue !== '')), 0, 10),
            'items' => $items,
        ];
    }

    /**
     * @return array{mime_type: string, data: string}|null
     */
    private function fileParts(WhatsappMessage $message): ?array
    {
        $disk = Storage::disk(WhatsappMessage::MEDIA_DISK);
        $path = (string) $message->media_path;

        if ($path === '' || ! $disk->exists($path)) {
            return null;
        }

        return [
            'mime_type' => $message->media_mime ?: ($message->type === WhatsappMessageType::Audio ? 'audio/ogg' : 'image/jpeg'),
            'data' => base64_encode((string) $disk->get($path)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @param  array<string, mixed>  $generationConfig
     */
    private function generate(string $model, array $parts, array $generationConfig): ?string
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.api_key')])
                ->timeout(max(30, (int) config('services.gemini.timeout', 60)))
                ->retry(2, 1500, throw: false)
                ->post(rtrim((string) config('services.gemini.base_url'), '/').'/models/'.$model.':generateContent', [
                    'contents' => [['role' => 'user', 'parts' => $parts]],
                    'generationConfig' => $generationConfig,
                ]);
        } catch (Throwable $exception) {
            Log::warning('Asistente WhatsApp: Gemini no respondió', ['model' => $model, 'error' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Asistente WhatsApp: Gemini devolvió un error', ['model' => $model, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);

            return null;
        }

        return collect($response->json('candidates.0.content.parts') ?? [])
            ->map(fn ($part): string => (string) ($part['text'] ?? ''))
            ->implode('');
    }

    /**
     * @return array<string, mixed>
     */
    private static function imageSchema(): array
    {
        $string = ['type' => 'STRING'];

        return [
            'type' => 'OBJECT',
            'properties' => [
                'image_kind' => ['type' => 'STRING', 'enum' => array_map(fn (WhatsappImageKind $kind): string => $kind->value, WhatsappImageKind::cases())],
                'description' => $string,
                'legibility' => ['type' => 'STRING', 'enum' => ['legible', 'dudosa', 'ilegible']],
                'overall_confidence' => ['type' => 'NUMBER'],
                'doctor_name' => $string,
                'prescription_date' => $string,
                'issues' => ['type' => 'ARRAY', 'items' => $string],
                'items' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'medication_name' => $string,
                            'active_ingredient' => $string,
                            'concentration' => $string,
                            'presentation' => $string,
                            'quantity_text' => $string,
                            'dosage_instructions' => $string,
                            'confidence' => ['type' => 'NUMBER'],
                            'is_controlled_substance' => ['type' => 'BOOLEAN'],
                            'unusual_note' => $string,
                        ],
                        'required' => ['medication_name', 'confidence'],
                    ],
                ],
                'payment' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'amount' => $string,
                        'reference' => $string,
                        'bank' => $string,
                        'destination_bank' => $string,
                        'date' => $string,
                        'destination' => $string,
                        'concept' => $string,
                    ],
                ],
            ],
            'required' => ['image_kind', 'description', 'legibility', 'items'],
        ];
    }
}
