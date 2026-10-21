<?php

namespace App\Http\Requests;

use App\Models\Church;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSegmentMemberRequest extends FormRequest
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
        /** @var Church $church */
        $church = $this->route('church');

        return [
            'person_id' => [
                'required',
                'integer',
                Rule::exists('people', 'id')
                    ->where(fn ($query) => $query->where('church_id', $church->getKey())),
            ],
        ];
    }
}
