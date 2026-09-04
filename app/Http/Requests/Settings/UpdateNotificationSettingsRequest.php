<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email_enabled' => 'nullable|boolean',
            'push_enabled' => 'nullable|boolean',
            'in_app_enabled' => 'nullable|boolean',
            'transaction_notifications' => 'nullable|boolean',
            'report_notifications' => 'nullable|boolean',
            'system_notifications' => 'nullable|boolean',
            'email_digest_frequency' => 'sometimes|string|in:daily,weekly,monthly',
            'push_on_transaction' => 'nullable|boolean',
            'daily_summary' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'email_digest_frequency.in' => 'La frecuencia del resumen no es válida',
        ];
    }
}