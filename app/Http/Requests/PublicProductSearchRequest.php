<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicProductSearchRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'ofertas' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.max' => 'La búsqueda no puede superar :max caracteres.',
            'category_id.integer' => 'La categoría no es válida.',
            'category_id.min' => 'La categoría no es válida.',
        ];
    }

    public function term(): string
    {
        return trim((string) $this->query('q', ''));
    }

    public function categoryId(): ?int
    {
        $categoryId = (int) $this->query('category_id', 0);

        return $categoryId > 0 ? $categoryId : null;
    }

    public function onlyOffers(): bool
    {
        return $this->boolean('ofertas');
    }
}
