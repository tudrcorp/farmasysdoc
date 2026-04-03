<?php

namespace App\Http\Requests\BdvConciliation;

use Illuminate\Foundation\Http\FormRequest;

class GetMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cedulaPagador' => ['required', 'string', 'max:32'],
            'telefonoPagador' => ['required', 'string', 'max:32'],
            'telefonoDestino' => ['required', 'string', 'max:32'],
            'referencia' => ['required', 'string', 'max:64'],
            'fechaPago' => ['required', 'string', 'date_format:Y-m-d'],
            'importe' => ['required', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'bancoOrigen' => ['required', 'string', 'max:16'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fechaPago.date_format' => 'La fecha de pago debe usar el formato AAAA-MM-DD (sin barras).',
            'importe.regex' => 'El importe debe usar punto (.) como separador decimal, sin comas.',
        ];
    }

    /**
     * Campos exactos del JSON de entrada del manual BDV.
     *
     * @param  array<string, string>  $validated
     * @return array<string, string>
     */
    public static function movementPayloadFromValidated(array $validated): array
    {
        return [
            'cedulaPagador' => $validated['cedulaPagador'],
            'telefonoPagador' => $validated['telefonoPagador'],
            'telefonoDestino' => $validated['telefonoDestino'],
            'referencia' => $validated['referencia'],
            'fechaPago' => $validated['fechaPago'],
            'importe' => $validated['importe'],
            'bancoOrigen' => $validated['bancoOrigen'],
        ];
    }

    /**
     * Campos exactos del JSON de entrada del manual BDV.
     *
     * @return array<string, string>
     */
    public function movementPayload(): array
    {
        /** @var array<string, string> $validated */
        $validated = $this->validated();

        return self::movementPayloadFromValidated($validated);
    }
}
