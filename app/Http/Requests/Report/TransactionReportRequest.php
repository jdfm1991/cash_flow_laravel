<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\TransactionType;

class TransactionReportRequest extends FormRequest
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
            'type' => 'nullable|in:' . implode(',', TransactionType::values()),
            'account_id' => 'nullable|exists:accounts,id,is_active,1',
            'category_id' => 'nullable|exists:categories,id,is_active,1',
            'format' => 'nullable|in:json,excel,pdf,csv',
        ];
    }
}