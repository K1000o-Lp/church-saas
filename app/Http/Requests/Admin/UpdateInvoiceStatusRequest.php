<?php

namespace App\Http\Requests\Admin;

use App\Support\Billing\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(InvoiceStatus::all())],
            'reason' => ['nullable', 'string', 'max:500', 'required_if:status,'.InvoiceStatus::VOID],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required_if' => 'Indica el motivo de la anulación.',
        ];
    }
}
