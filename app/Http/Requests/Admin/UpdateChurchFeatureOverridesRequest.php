<?php

namespace App\Http\Requests\Admin;

use App\Support\Plans\PlanFeatures;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class UpdateChurchFeatureOverridesRequest extends FormRequest
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
            'features' => ['required', 'array'],
            'features.*' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $features = $this->input('features', []);

            if (! is_array($features)) {
                return;
            }

            foreach ($features as $feature => $mode) {
                if (! in_array($feature, PlanFeatures::keys(), true)) {
                    $validator->errors()->add(
                        "features.{$feature}",
                        'La función indicada no es válida.',
                    );

                    continue;
                }

                if (! in_array($mode, ['inherit', 'enabled', 'disabled'], true)) {
                    $validator->errors()->add(
                        "features.{$feature}",
                        'Selecciona heredar, habilitar o deshabilitar.',
                    );
                }
            }
        });
    }
}
