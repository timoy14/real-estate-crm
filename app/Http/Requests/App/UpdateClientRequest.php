<?php

namespace App\Http\Requests\App;

use App\Models\Contact;

class UpdateClientRequest extends StoreClientRequest
{
    public function authorize(): bool
    {
        /** @var Contact $contact */
        $contact = $this->route('client');

        return $this->user()->can('update', $contact);
    }
}
