<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class AccountReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'format' => 'nullable|in:json,excel,pdf',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.date' => 'La fecha de inicio debe ser una fecha válida',
            'end_date.date' => 'La fecha de fin debe ser una fecha válida',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o mayor a la fecha de inicio',
            'format.in' => 'El formato debe ser json, excel o pdf',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_date' => $this->input('start_date', now()->startOfMonth()->toDateString()),
            'end_date' => $this->input('end_date', now()->toDateString()),
        ]);
    }
}