<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\TransactionType;

class ReconvertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:' . implode(',', TransactionType::values()),
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'target_currency_id' => 'required|exists:currencies,id,is_active,1',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de transacción es requerido',
            'start_date.required' => 'La fecha de inicio es requerida',
            'end_date.required' => 'La fecha de fin es requerida',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o mayor a la fecha de inicio',
            'target_currency_id.required' => 'La moneda destino es requerida',
            'target_currency_id.exists' => 'La moneda destino no existe o está inactiva',
        ];
    }
}