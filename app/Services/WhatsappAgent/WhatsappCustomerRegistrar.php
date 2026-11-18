<?php

namespace App\Services\WhatsappAgent;

use App\Models\Client;
use App\Models\WhatsappContact;
use App\Models\WhatsappConversation;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Str;

/**
 * Registra (o enlaza) al cliente de la caja con los datos que da por WhatsApp, buscándolo por cédula,
 * para que el cajero lo encuentre de inmediato al cobrar.
 *
 * Si la cédula ya existe con otro nombre no se sobrescribe nada: se enlaza y se marca para que el analista verifique.
 */
final class WhatsappCustomerRegistrar
{
    public const CREATED_BY = 'whatsapp:asistente';

    /**
     * Tipo de documento de la caja ({@see Client::$document_type}) según la letra de la cédula.
     *
     * @var array<string, string>
     */
    private const DOCUMENT_TYPES = [
        'V' => 'CC',
        'E' => 'CE',
        'J' => 'RIF',
        'G' => 'RIF',
        'P' => 'PAS',
    ];

    /**
     * @return array{status: 'created'|'linked'|'mismatch', client_id: int, message: string}
     */
    public function register(WhatsappConversation $conversation, string $fullName, string $documentLetter, string $documentNumber): array
    {
        $contact = $conversation->contact;
        $fullName = Str::of($fullName)->squish()->title()->limit(160, '')->toString();
        $documentLetter = mb_strtoupper(trim($documentLetter)) ?: 'V';
        $digits = (string) preg_replace('/\D+/', '', $documentNumber);

        $contact->forceFill([
            'full_name' => $fullName,
            'document_type' => $documentLetter,
            'document_number' => $digits,
        ])->save();

        $client = $this->findClientByDocument($documentLetter, $digits);

        if ($client instanceof Client) {
            return $this->linkExisting($contact, $client, $fullName);
        }

        $client = Client::query()->create([
            'name' => $fullName,
            'document_type' => self::DOCUMENT_TYPES[$documentLetter] ?? 'CC',
            'document_number' => $digits,
            'email' => 'whatsapp+'.Str::uuid()->toString().'@whatsapp.invalid',
            'phone' => $contact->localPhone(),
            'address' => filled($conversation->delivery_address) ? mb_substr((string) $conversation->delivery_address, 0, 255) : 'No indicada',
            'city' => 'No indicada',
            'state' => 'No indicado',
            'country' => 'Venezuela',
            'status' => 'active',
            'customer_discount' => 0,
            'created_by' => self::CREATED_BY,
            'updated_by' => self::CREATED_BY,
        ]);

        $contact->forceFill([
            'client_id' => $client->getKey(),
            'client_data_mismatch' => false,
            'client_data_mismatch_note' => null,
        ])->save();

        AuditLogger::record(
            'whatsapp_client_registered',
            'WhatsApp · Cliente registrado por el asistente: '.$fullName,
            Client::class,
            $client->getKey(),
            $fullName,
            ['module' => 'whatsapp_agent', 'conversation_id' => $conversation->getKey()],
        );

        return [
            'status' => 'created',
            'client_id' => (int) $client->getKey(),
            'message' => 'Cliente registrado. Ya aparece en la caja con su cédula.',
        ];
    }

    /**
     * @return array{status: 'linked'|'mismatch', client_id: int, message: string}
     */
    private function linkExisting(WhatsappContact $contact, Client $client, string $fullName): array
    {
        $matches = self::namesLookAlike((string) $client->name, $fullName);

        if ($matches && $this->isBlankValue($client->phone)) {
            $client->forceFill(['phone' => $contact->localPhone(), 'updated_by' => self::CREATED_BY])->save();
        }

        $contact->forceFill([
            'client_id' => $client->getKey(),
            'client_data_mismatch' => ! $matches,
            'client_data_mismatch_note' => $matches
                ? null
                : 'La cédula ya está registrada a nombre de «'.$client->name.'» y el cliente dijo llamarse «'.$fullName.'».',
        ])->save();

        return [
            'status' => $matches ? 'linked' : 'mismatch',
            'client_id' => (int) $client->getKey(),
            'message' => $matches
                ? 'El cliente ya estaba registrado; quedó enlazado a esta conversación.'
                : 'La cédula ya está registrada con otro nombre. No se cambió nada; el analista lo verificará. Sigue atendiendo con normalidad.',
        ];
    }

    private function findClientByDocument(string $letter, string $digits): ?Client
    {
        if ($digits === '') {
            return null;
        }

        $variants = [$digits, $letter.$digits, $letter.'-'.$digits, $letter.'.'.$digits, $letter.' '.$digits];

        if (strlen($digits) > 3) {
            $variants[] = number_format((int) $digits, 0, '', '.');
            $variants[] = $letter.'-'.number_format((int) $digits, 0, '', '.');
        }

        return Client::query()
            ->whereIn('document_number', array_values(array_unique($variants)))
            ->orderBy('id')
            ->first();
    }

    /**
     * Considera que es la misma persona si coincide al menos un nombre y un apellido (sin acentos ni mayúsculas).
     */
    public static function namesLookAlike(string $stored, string $given): bool
    {
        $tokenize = static fn (string $value): array => array_values(array_filter(
            explode(' ', Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z ]/', ' ')->squish()->toString()),
            static fn (string $token): bool => mb_strlen($token) >= 3,
        ));

        $storedTokens = $tokenize($stored);
        $givenTokens = $tokenize($given);

        if ($storedTokens === [] || $givenTokens === []) {
            return false;
        }

        $common = array_intersect($storedTokens, $givenTokens);

        return count($common) >= min(2, count($storedTokens), count($givenTokens));
    }

    private function isBlankValue(mixed $value): bool
    {
        return blank($value) || in_array(trim((string) $value), ['—', '-', 'No indicado', 'No indicada'], true);
    }
}
