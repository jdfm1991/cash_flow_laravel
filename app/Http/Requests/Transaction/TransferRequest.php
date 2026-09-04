<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'from_account_id' => 'required|exists:bank_accounts,id,is_active,1',
            'to_account_id' => 'required|exists:bank_accounts,id,is_active,1|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'currency_id' => 'required|exists:currencies,id,is_active,1',
            'date' => 'required|date|before_or_equal:today',
            'description' => 'required|string|max:255',
            'reference' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'from_account_id.required' => 'La cuenta de origen es requerida',
            'to_account_id.required' => 'La cuenta de destino es requerida',
            'to_account_id.different' => 'La cuenta origen y destino deben ser diferentes',
            'amount.required' => 'El monto es requerido',
            'amount.min' => 'El monto debe ser mayor a 0',
            'date.before_or_equal' => 'La fecha no puede ser futura',
        ];
    }
}