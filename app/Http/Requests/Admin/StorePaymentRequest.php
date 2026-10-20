<?php

namespace App\Http\Requests\Admin;

use App\Support\Billing\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,9}(?:\.\d{1,2})?$/'],
            'method' => ['required', 'string', Rule::in(PaymentMethod::all())],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'El monto debe ser un número positivo con máximo dos decimales.',
            'amount.gt' => 'El monto debe ser mayor que cero.',
            'paid_at.before_or_equal' => 'La fecha del pago no puede estar en el futuro.',
        ];
    }
}
