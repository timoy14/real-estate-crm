<?php

namespace App\Http\Requests\Public;

use App\Models\Contact;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            // Collapse whitespace and newlines; the name ends up in titles and mail.
            $this->merge(['name' => trim((string) preg_replace('/\s+/u', ' ', $this->input('name')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'string', 'email', 'max:254', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email', function ($attr, $value, $fail) {
                if (filled($value) && strlen((string) Contact::normalizePhone($value)) < 7) {
                    $fail('Enter a valid phone number.');
                }
            }],
            'message' => ['required', 'string', 'max:2000'],
            'wants_viewing' => ['nullable', 'boolean'],
            'preferred_contact' => ['nullable', Rule::in(['email', 'phone', 'whatsapp'])],
            'consent' => ['accepted'],
            'website' => ['nullable'],
        ];
    }

    /** A filled honeypot gets the success response even when the rest is invalid. */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->filled('website')) {
            throw new HttpResponseException(back()->with('success', 'Thanks! We will be in touch soon.'));
        }

        parent::failedValidation($validator);
    }
}
