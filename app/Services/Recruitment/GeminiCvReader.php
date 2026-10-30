<?php

namespace App\Services\Recruitment;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

/**
 * Lee un CV con la API de Gemini y devuelve sus datos sin normalizar.
 */
class GeminiCvReader
{
    private const string PROMPT = <<<'PROMPT'
    Eres un asistente de recursos humanos de una farmacia en Venezuela. Extrae los datos del candidato de este curriculum vitae.
    Reglas:
    - Copia solo lo que aparece en el documento. Nunca inventes ni completes datos; si un dato no aparece, devuélvelo como null.
    - national_id es la cédula de identidad venezolana, con su letra si aparece (V o E).
    - address es la dirección de habitación del candidato, no la de una empresa donde trabajó.
    - birth_date va en formato YYYY-MM-DD.
    - education y work_experience son resúmenes breves en español, un elemento por línea.
    - years_of_experience es el total de años de experiencia laboral, redondeado hacia abajo.
    - uncertain_fields lista los campos que leíste con dudas (texto borroso, cortado o ambiguo).
    - photo indica dónde está la foto de la cara del candidato: page es la página empezando en 1 y box_2d es [ymin, xmin, ymax, xmax] en escala de 0 a 1000. Si no hay foto de su cara, devuelve null. Ignora logos y otras imágenes.
    PROMPT;

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    /**
     * @return array<string, mixed>
     */
    public function read(string $absolutePath, string $mimeType): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Falta configurar GEMINI_API_KEY.');
        }

        $response = Http::timeout((int) config('services.gemini.timeout', 60))
            ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.api_key')])
            ->post($this->endpoint(), [
                'contents' => [['parts' => [$this->documentPart($absolutePath, $mimeType), ['text' => self::PROMPT]]]],
                'generationConfig' => [
                    'temperature' => 0,
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $this->responseSchema(),
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Gemini respondió con error {$response->status()}.");
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $decoded = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini no devolvió datos legibles.');
        }

        return $decoded;
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.gemini.base_url'), '/')
            .'/models/'.config('services.gemini.model').':generateContent';
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPart(string $absolutePath, string $mimeType): array
    {
        if (str_contains($mimeType, 'wordprocessingml')) {
            return ['text' => $this->wordDocumentText($absolutePath)];
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            throw new RuntimeException('No se pudo abrir el archivo del CV.');
        }

        return ['inline_data' => ['mime_type' => $mimeType, 'data' => base64_encode($contents)]];
    }

    private function wordDocumentText(string $absolutePath): string
    {
        $zip = new ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('No se pudo abrir el documento de Word.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('El documento de Word no tiene contenido legible.');
        }

        $withLineBreaks = preg_replace('/<\/w:p>|<w:br[^>]*>|<w:tab[^>]*>/', "\n", $xml) ?? $xml;

        return trim(html_entity_decode(strip_tags($withLineBreaks), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        $nullableText = ['type' => 'STRING', 'nullable' => true];

        return [
            'type' => 'OBJECT',
            'properties' => [
                'first_name' => $nullableText,
                'last_name' => $nullableText,
                'national_id' => $nullableText,
                'phone' => $nullableText,
                'email' => $nullableText,
                'address' => $nullableText,
                'birth_date' => $nullableText,
                'desired_position' => $nullableText,
                'years_of_experience' => ['type' => 'INTEGER', 'nullable' => true],
                'education' => $nullableText,
                'work_experience' => $nullableText,
                'uncertain_fields' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'photo' => [
                    'type' => 'OBJECT',
                    'nullable' => true,
                    'properties' => [
                        'page' => ['type' => 'INTEGER'],
                        'box_2d' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
                    ],
                    'required' => ['page', 'box_2d'],
                ],
            ],
            'required' => ['first_name', 'last_name', 'national_id', 'phone', 'email', 'address'],
        ];
    }
}
