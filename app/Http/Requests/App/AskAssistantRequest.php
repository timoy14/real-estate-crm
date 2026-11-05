<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AskAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // conversation ownership and scope visibility are checked in the controller
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
            'scope_type' => ['nullable', 'required_with:scope_id', Rule::in(['contact', 'property', 'deal'])],
            'scope_id' => ['nullable', 'required_with:scope_type', 'integer', 'min:1'],
        ];
    }
}
