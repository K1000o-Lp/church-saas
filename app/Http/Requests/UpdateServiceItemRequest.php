<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::TeamsManage);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'notes' => trim((string) $this->input('notes')) ?: null,
            'position' => (int) $this->input('position', 0),
            'ministry_team_id' => $this->input('ministry_team_id') ?: null,
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
            'title' => ['required', 'string', 'max:200'],
            'ministry_team_id' => [
                'nullable',
                'integer',
                Rule::exists('ministry_teams', 'id')->where('church_id', $church->getKey()),
            ],
            'position' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
