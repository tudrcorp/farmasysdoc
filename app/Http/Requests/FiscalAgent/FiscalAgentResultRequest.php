<?php

namespace App\Http\Requests\FiscalAgent;

use App\Services\Fiscal\FiscalAgentQueue;
use App\Services\Fiscal\FiscalZReportRecorder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiscalAgentResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'outcome' => ['required', 'string', Rule::in([
                FiscalAgentQueue::OUTCOME_PRINTED,
                FiscalAgentQueue::OUTCOME_FAILED,
                FiscalAgentQueue::OUTCOME_UNCERTAIN,
                FiscalAgentQueue::OUTCOME_SIMULATED,
            ])],
            'fiscal_number' => ['nullable', 'string', 'max:20'],
            'printer_computes_igtf' => ['nullable', 'boolean'],
            'printer_serial' => ['nullable', 'string', 'max:40'],
            'z_number' => ['nullable', 'string', 'max:20'],
            'printer_datetime' => ['nullable', 'date'],
            'printer_total_ves' => ['nullable', 'numeric', 'min:0'],
            'error_code' => ['nullable', 'string', 'max:40'],
            'error_message' => ['nullable', 'string', 'max:2000'],
            'raw' => ['nullable', 'array'],
            'z_reports' => ['nullable', 'array', 'max:400'],
            'z_reports.*' => ['array'],
            'z_reports.*.z_number' => ['required', 'integer', 'min:1'],
            'z_reports.*.z_datetime' => ['required', 'date'],
            'z_reports.*.last_invoice_number' => ['required', 'integer', 'min:0'],
            'z_reports.*.last_credit_note_number' => ['nullable', 'integer', 'min:0'],
            'z_reports.*.last_debit_note_number' => ['nullable', 'integer', 'min:0'],
            'z_reports.*.last_non_fiscal_number' => ['nullable', 'integer', 'min:0'],
            'z_reports.*.last_invoice_datetime' => ['nullable', 'date'],
            'z_reports.*.field_count' => ['nullable', 'integer', 'min:0'],
            'z_reports.*.raw_record' => ['nullable', 'string', 'max:20000'],
        ], $this->zReportAmountRules());
    }

    /**
     * Cada monto del Z necesita su propia regla: Laravel descarta de validated() las claves de un arreglo
     * que no tienen regla, y sin esto los montos y la trama cruda nunca llegaban al servidor.
     *
     * @return array<string, array<int, string>>
     */
    private function zReportAmountRules(): array
    {
        $rules = [];

        foreach (FiscalZReportRecorder::AGENT_AMOUNT_FIELDS as $field) {
            $rules['z_reports.*.'.$field] = ['nullable', 'numeric'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'outcome.required' => 'Debe indicar el resultado (printed, failed, uncertain o simulated).',
            'outcome.in' => 'Resultado inválido: use printed, failed, uncertain o simulated.',
            'printer_computes_igtf.boolean' => 'printer_computes_igtf debe ser verdadero o falso.',
            'printer_datetime.date' => 'La fecha de la máquina fiscal no es válida.',
            'printer_total_ves.numeric' => 'El total de la máquina fiscal debe ser numérico.',
            'z_reports.*.z_number.required' => 'Cada reporte Z debe traer su número.',
            'z_reports.*.z_datetime.required' => 'Cada reporte Z debe traer su fecha.',
            'z_reports.*.last_invoice_number.required' => 'Cada reporte Z debe traer el número de la última factura.',
        ];
    }
}
