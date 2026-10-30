<?php

namespace App\Services\AiAgent;

use Closure;
use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use stdClass;

/**
 * Llamada a Gemini con herramientas y respuesta en vivo. Conserva las partes del modelo
 * tal como llegan, porque Gemini exige recibirlas de vuelta con sus firmas.
 */
class GeminiAgentClient
{
    /**
     * @param  list<array<string, mixed>>  $contents
     * @param  list<array<string, mixed>>  $tools
     * @param  (Closure(string): void)|null  $onText  Recibe cada fragmento de texto apenas llega.
     * @param  bool  $forceAnswer  Prohíbe más herramientas: el modelo debe responder con lo que ya tiene.
     * @return array{content: array<string, mixed>, input_tokens: int, output_tokens: int}
     */
    public function generate(string $systemPrompt, array $contents, array $tools, ?Closure $onText = null, bool $forceAnswer = false): array
    {
        $apiKey = (string) config('services.gemini.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('Falta configurar GEMINI_API_KEY.');
        }

        $response = Http::timeout((int) config('ai_agent.timeout'))
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->withOptions(['stream' => true])
            ->post($this->endpoint(), [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => $contents,
                'tools' => [['functionDeclarations' => $tools]],
                'toolConfig' => ['functionCallingConfig' => ['mode' => $forceAnswer ? 'NONE' : 'AUTO']],
            ]);

        if ($response->status() === 402) {
            throw new RuntimeException('Se acabó el saldo de Gemini. Recarga créditos en Google AI Studio.');
        }

        if ($response->failed()) {
            Log::warning('Gemini agent request failed', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 1000)]);

            throw new RuntimeException("Gemini respondió con error {$response->status()}.");
        }

        $parts = [];
        $usage = [];

        foreach ($this->events($response->toPsrResponse()->getBody()) as $event) {
            $usage = is_array($event['usageMetadata'] ?? null) ? $event['usageMetadata'] : $usage;

            foreach ($event['candidates'][0]['content']['parts'] ?? [] as $part) {
                $parts = $this->mergePart($parts, $part, $onText);
            }
        }

        if ($parts === []) {
            throw new RuntimeException('Gemini no devolvió una respuesta.');
        }

        return [
            'content' => ['role' => 'model', 'parts' => $parts],
            'input_tokens' => (int) ($usage['promptTokenCount'] ?? 0),
            'output_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0),
        ];
    }

    /**
     * Une los fragmentos de texto. Los demás tipos de parte se conservan intactos.
     *
     * @param  list<array<string, mixed>>  $parts
     * @param  array<string, mixed>  $part
     * @return list<array<string, mixed>>
     */
    private function mergePart(array $parts, array $part, ?Closure $onText): array
    {
        if (isset($part['functionCall']) && empty($part['functionCall']['args'])) {
            $part['functionCall']['args'] = new stdClass;
        }

        $isText = is_string($part['text'] ?? null) && ! ($part['thought'] ?? false);

        if ($isText && $onText !== null) {
            $onText($part['text']);
        }

        $last = array_key_last($parts);

        if ($isText && $last !== null && is_string($parts[$last]['text'] ?? null) && ! ($parts[$last]['thought'] ?? false)) {
            $parts[$last] = [...$parts[$last], ...$part, 'text' => $parts[$last]['text'].$part['text']];

            return $parts;
        }

        $parts[] = $part;

        return $parts;
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function events(StreamInterface $body): Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($lineEnd = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $lineEnd), "\r");
                $buffer = substr($buffer, $lineEnd + 1);

                if (str_starts_with($line, 'data:')) {
                    $event = json_decode(trim(substr($line, 5)), true);

                    if (is_array($event)) {
                        yield $event;
                    }
                }
            }
        }
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.gemini.base_url'), '/')
            .'/models/'.config('ai_agent.model').':streamGenerateContent?alt=sse';
    }
}
