<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMinistryTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::TeamsManage);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'description' => trim((string) $this->input('description')) ?: null,
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

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('ministry_teams', 'name')
                    ->where(fn ($query) => $query->where('church_id', $church->getKey())),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }
}
