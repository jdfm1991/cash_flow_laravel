<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class MonthlyComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => 'nullable|integer|min:2000|max:' . (date('Y') + 1),
            'months' => 'nullable|integer|min:1|max:24',
            'format' => 'nullable|in:json,excel,pdf',
        ];
    }

    public function messages(): array
    {
        return [
            'year.integer' => 'El año debe ser un número entero',
            'year.min' => 'El año debe ser mayor o igual a 2000',
            'year.max' => 'El año no puede ser mayor a ' . (date('Y') + 1),
            'months.integer' => 'El número de meses debe ser un número entero',
            'months.min' => 'El número de meses debe ser al menos 1',
            'months.max' => 'El número de meses no puede ser mayor a 24',
            'format.in' => 'El formato debe ser json, excel o pdf',
        ];
    }

    /**
     * ✅ Preparar datos con valores por defecto
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'year' => $this->input('year', (int) date('Y')),
            'months' => $this->input('months', 12),
        ]);
    }

    /**
     * Obtener datos validados con valores por defecto
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        if (!isset($data['year'])) {
            $data['year'] = (int) date('Y');
        }

        if (!isset($data['months'])) {
            $data['months'] = 12;
        }

        return $data;
    }
}