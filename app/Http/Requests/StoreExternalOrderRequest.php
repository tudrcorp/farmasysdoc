<?php

namespace App\Http\Requests;

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'partner_company' => [
                'required',
                'string',
                'max:255',
                Rule::exists('partner_companies', 'code'),
            ],
            'order_number' => ['nullable', 'string', 'max:255', Rule::unique('orders', 'order_number')],
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'convenio_type' => ['nullable', Rule::enum(ConvenioType::class)],
            'convenio_partner_name' => ['nullable', 'string', 'max:255'],
            'convenio_reference' => ['nullable', 'string', 'max:255'],
            'convenio_notes' => ['nullable', 'string'],
            'delivery_recipient_name' => ['nullable', 'string', 'max:255'],
            'delivery_phone' => ['nullable', 'string', 'max:40'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'delivery_city' => ['nullable', 'string', 'max:100'],
            'delivery_state' => ['nullable', 'string', 'max:100'],
            'delivery_notes' => ['nullable', 'string'],
            'scheduled_delivery_at' => ['nullable', 'date'],
            'dispatched_at' => ['nullable', 'date'],
            'delivered_at' => ['nullable', 'date'],
            'delivery_assignee' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'service_order' => ['nullable', 'array'],
            'service_order.patient_name' => ['nullable', 'string', 'max:255'],
            'service_order.patient_document' => ['nullable', 'string', 'max:255'],
            'service_order.patient_phone' => ['nullable', 'string', 'max:40'],
            'service_order.patient_email' => ['nullable', 'string', 'max:255'],
            'service_order.diagnosis' => ['nullable', 'string'],
            'service_order.external_reference' => ['nullable', 'string', 'max:255'],
            'service_order.priority' => ['nullable', 'string', 'max:255'],
            'service_order.service_type' => ['nullable', 'string', 'max:255'],
            'service_order.status' => ['nullable', 'string', 'max:255'],
            'service_order.items' => ['nullable', 'array'],
            'service_order.items.*.name' => ['nullable', 'string', 'max:500'],
            'service_order.items.*.indicacion' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.inventory_id' => ['nullable', 'integer', Rule::exists('inventories', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.product_name_snapshot' => ['nullable', 'string', 'max:255'],
            'items.*.sku_snapshot' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'partner_company.required' => 'Debe indicar el código de compañía aliada (campo partner_company).',
            'partner_company.exists' => 'La compañía aliada no existe o el código no coincide con un registro en el sistema.',
            'items.required' => 'Debe enviar al menos una línea en items.',
            'items.min' => 'Debe enviar al menos una línea en items.',
            'items.*.product_id.required' => 'Cada línea debe incluir product_id.',
            'items.*.quantity.gt' => 'La cantidad de cada línea debe ser mayor que cero.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'partner_company' => 'código de compañía aliada',
        ];
    }
}
