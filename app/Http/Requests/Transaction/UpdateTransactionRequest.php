<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\PaymentMethod;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'account_id' => 'sometimes|exists:accounts,id,is_active,1',
            'category_id' => 'sometimes|exists:categories,id,is_active,1',
            // ✅ Cambiar de 'sometimes' a 'nullable' para permitir NULL
            'bank_account_id' => 'nullable|exists:bank_accounts,id,is_active,1',
            'amount' => 'sometimes|numeric|min:0.01',
            'currency_id' => 'sometimes|exists:currencies,id,is_active,1',
            'date' => 'nullable|date|before_or_equal:today',
            'description' => 'sometimes|string|max:255',
            'reference' => 'nullable|string|max:100',
            'payment_method' => 'nullable|in:' . implode(',', PaymentMethod::values()),
            'receipt_path' => 'nullable|string|max:255',
        ];
    }

    /**
     * Preparar los datos para la validación
     * ✅ Convierte 0 a NULL para evitar errores de foreign key
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'bank_account_id' => $this->input('bank_account_id') == 0 ? null : $this->input('bank_account_id'),
        ]);
    }

    public function messages(): array
    {
        return [
            'account_id.exists' => 'La cuenta contable no existe o está inactiva',
            'category_id.exists' => 'La categoría no existe o está inactiva',
            'bank_account_id.exists' => 'La cuenta bancaria no existe o está inactiva',
            'amount.numeric' => 'El monto debe ser un número',
            'amount.min' => 'El monto debe ser mayor a 0',
            'currency_id.exists' => 'La moneda no existe o está inactiva',
            'date.before_or_equal' => 'La fecha no puede ser futura',
            'description.max' => 'La descripción no puede exceder 255 caracteres',
            'payment_method.in' => 'El método de pago no es válido',
        ];
    }
}