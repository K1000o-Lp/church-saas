<?php

namespace App\Http\Requests\Admin;

use App\Support\Plans\PlanResources;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class UpdateChurchLimitOverridesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('platform')->check();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'limits' => ['required', 'array'],
            'limits.*' => ['required', 'array'],
            'limits.*.mode' => ['required', 'string'],
            'limits.*.value' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $limits = $this->input('limits', []);

            if (! is_array($limits)) {
                return;
            }

            foreach ($limits as $resource => $entry) {
                if (! in_array($resource, PlanResources::keys(), true)) {
                    $validator->errors()->add(
                        "limits.{$resource}",
                        'El recurso indicado no es válido.',
                    );

                    continue;
                }

                $mode = is_array($entry) ? ($entry['mode'] ?? null) : null;

                if (! in_array($mode, ['inherit', 'unlimited', 'custom'], true)) {
                    $validator->errors()->add(
                        "limits.{$resource}.mode",
                        'Selecciona heredar, ilimitado o un valor personalizado.',
                    );

                    continue;
                }

                if ($mode === 'custom' && ! array_key_exists('value', $entry)) {
                    $validator->errors()->add(
                        "limits.{$resource}.value",
                        'Indica el límite personalizado.',
                    );
                }
            }
        });
    }
}
