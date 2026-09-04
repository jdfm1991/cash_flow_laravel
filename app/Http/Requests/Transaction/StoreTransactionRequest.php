<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\TransactionType;
use App\Enums\PaymentMethod;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type' => 'nullable|in:' . implode(',', TransactionType::values()),
            'account_id' => 'required|exists:accounts,id,is_active,1',
            'category_id' => 'required|exists:categories,id,is_active,1',
            'bank_account_id' => 'required|exists:bank_accounts,id,is_active,1',
            'amount' => 'required|numeric|min:0.01',
            'currency_id' => 'required|exists:currencies,id,is_active,1',
            'date' => 'nullable|date|before_or_equal:today',
            'description' => 'required|string|max:255',
            'reference' => 'nullable|string|max:100',
            'payment_method' => 'nullable|in:' . implode(',', PaymentMethod::values()),
            'receipt_path' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'El tipo de transacción no es válido',
            'account_id.required' => 'La cuenta contable es requerida',
            'account_id.exists' => 'La cuenta contable no existe o está inactiva',
            'category_id.required' => 'La categoría es requerida',
            'category_id.exists' => 'La categoría no existe o está inactiva',
            'bank_account_id.required' => 'La cuenta bancaria es requerida',
            'bank_account_id.exists' => 'La cuenta bancaria no existe o está inactiva',
            'amount.required' => 'El monto es requerido',
            'amount.numeric' => 'El monto debe ser un número',
            'amount.min' => 'El monto debe ser mayor a 0',
            'currency_id.required' => 'La moneda es requerida',
            'currency_id.exists' => 'La moneda no existe o está inactiva',
            'date.before_or_equal' => 'La fecha no puede ser futura',
            'description.required' => 'La descripción es requerida',
            'description.max' => 'La descripción no puede exceder 255 caracteres',
            'payment_method.in' => 'El método de pago no es válido',
        ];
    }
}