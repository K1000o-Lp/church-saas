<?php

namespace App\Http\Requests;

use App\Support\Auth\Permission;
use App\Support\Services\AssignmentStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVolunteerAssignmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::VolunteersManage);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(AssignmentStatus::values())],
        ];
    }
}
