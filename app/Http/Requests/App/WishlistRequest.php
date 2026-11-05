<?php

namespace App\Http\Requests\App;

use App\Enums\ListingType;
use App\Enums\PropertyType;
use App\Models\Contact;
use App\Models\Property;
use App\Models\Wishlist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Authorization follows the parent contact. */
class WishlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        $route = $this->route();
        $contact = $route->parameter('client');

        if (! $contact instanceof Contact) {
            $wishlist = $route->parameter('wishlist');
            $contact = $wishlist instanceof Wishlist ? $wishlist->contact : null;
        }

        return $contact !== null && $this->user()->can('update', $contact);
    }

    public function rules(): array
    {
        return [
            'listing_type' => ['required', Rule::enum(ListingType::class)],
            'property_types' => ['nullable', 'array'],
            'property_types.*' => [Rule::enum(PropertyType::class)],
            'min_budget' => ['nullable', 'integer', 'min:0'],
            'max_budget' => [
                'nullable', 'integer', 'min:0',
                Rule::when($this->filled('min_budget'), 'gte:min_budget'),
            ],
            'min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'min_bathrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'areas' => ['nullable', 'array'],
            'areas.*' => ['string', 'max:100'],
            'must_have_features' => ['nullable', 'array'],
            'must_have_features.*' => ['string', Rule::in(Property::FEATURES)],
            'move_in_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return ['max_budget.gte' => 'Max budget must be at least the min budget.'];
    }

    public function wishlistData(): array
    {
        $data = $this->validated();
        foreach (['property_types', 'areas', 'must_have_features'] as $k) {
            $data[$k] = array_values($data[$k] ?? []);
        }

        return $data;
    }
}
