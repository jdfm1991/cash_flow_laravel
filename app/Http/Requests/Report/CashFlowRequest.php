<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class CashFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'category_id' => 'nullable|exists:categories,id,is_active,1',
            'account_id' => 'nullable|exists:accounts,id,is_active,1',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'La fecha de inicio es requerida',
            'end_date.required' => 'La fecha de fin es requerida',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o mayor a la fecha de inicio',
        ];
    }
}