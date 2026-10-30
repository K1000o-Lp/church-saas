<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePipelineStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::PastoralManage);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'position' => (int) $this->input('position', 0),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Church $church */
        $church = $this->route('church');
        $stage = $this->route('stage');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('pipeline_stages', 'name')
                    ->where(fn ($query) => $query->where('church_id', $church->getKey()))
                    ->ignore($stage),
            ],
            'position' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
