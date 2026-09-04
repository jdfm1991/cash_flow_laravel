<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class YearlySummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_year' => 'nullable|integer|min:2000|max:' . (date('Y')),
            'end_year' => 'nullable|integer|min:2000|max:' . (date('Y')) . '|gte:start_year',
            'format' => 'nullable|in:json,excel,pdf',
        ];
    }

    public function messages(): array
    {
        return [
            'start_year.integer' => 'El año de inicio debe ser un número entero',
            'start_year.min' => 'El año de inicio debe ser mayor o igual a 2000',
            'start_year.max' => 'El año de inicio no puede ser mayor al año actual',
            'end_year.integer' => 'El año de fin debe ser un número entero',
            'end_year.min' => 'El año de fin debe ser mayor o igual a 2000',
            'end_year.max' => 'El año de fin no puede ser mayor al año actual',
            'end_year.gte' => 'El año de fin debe ser mayor o igual al año de inicio',
            'format.in' => 'El formato debe ser json, excel o pdf',
        ];
    }

    /**
     * ✅ Preparar datos con valores por defecto
     */
    protected function prepareForValidation(): void
    {
        $currentYear = (int) date('Y');
        
        $this->merge([
            'start_year' => $this->input('start_year', $currentYear - 5),
            'end_year' => $this->input('end_year', $currentYear),
        ]);
    }

    /**
     * Obtener datos validados con valores por defecto
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        $currentYear = (int) date('Y');

        if (!isset($data['start_year'])) {
            $data['start_year'] = $currentYear - 5;
        }

        if (!isset($data['end_year'])) {
            $data['end_year'] = $currentYear;
        }

        return $data;
    }
}