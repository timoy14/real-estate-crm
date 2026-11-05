<?php

namespace App\Http\Requests\App;

use App\Enums\ContactStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only admins may reassign.
        return $this->input('action') !== 'reassign' || $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'action' => ['required', Rule::in(['delete', 'reassign', 'set_status'])],
            'owner_id' => ['required_if:action,reassign', 'nullable', 'integer', 'exists:users,id'],
            'status' => ['required_if:action,set_status', 'nullable', Rule::enum(ContactStatus::class)],
        ];
    }
}
