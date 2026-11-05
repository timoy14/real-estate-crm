<?php

namespace App\Http\Requests\App;

use App\Enums\ContactRole;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Http\Requests\Concerns\ResolvesOwner;
use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    use ResolvesOwner;

    public function authorize(): bool
    {
        return $this->user()->can('create', Contact::class);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', Rule::enum(ContactRole::class)],
            'status' => ['nullable', Rule::enum(ContactStatus::class)],
            'source' => ['nullable', Rule::enum(ContactSource::class)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Enter an email or a phone number.',
            'roles.required' => 'Pick at least one role.',
            'roles.min' => 'Pick at least one role.',
        ];
    }

    /** Contact fields, with owner_id removed (the controller decides the owner). */
    public function contactData(): array
    {
        $data = $this->safe()->except('owner_id');
        $data['status'] ??= ContactStatus::NewLead->value;
        $data['tags'] = array_values(array_unique($data['tags'] ?? []));

        return $data;
    }
}
