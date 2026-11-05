<?php

namespace App\Http\Requests\App;

use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Http\Requests\Concerns\ResolvesOwner;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shared rules for creating and updating a property. */
class PropertyRequest extends FormRequest
{
    use ResolvesOwner;

    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property
            ? $this->user()->can('update', $property)
            : $this->user()->can('create', Property::class);
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'title' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'area' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'listing_type' => ['required', Rule::enum(ListingType::class)],
            'property_type' => ['required', Rule::enum(PropertyType::class)],
            'price' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'floor_area_sqft' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'lot_area_sqft' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'year_built' => ['nullable', 'integer', 'min:1600', 'max:'.(now()->year + 5)],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', Rule::in(Property::FEATURES)],
            'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['nullable', Rule::enum(PropertyStatus::class)],
            'is_published' => ['nullable', 'boolean'],
            'hide_exact_address' => ['nullable', 'boolean'],
            'owner_contact_id' => [
                'nullable', 'integer',
                Rule::exists('contacts', 'id')->where(function ($q) use ($user) {
                    $q->whereNull('deleted_at');
                    if (! $user->isAdmin()) {
                        // Own contacts, or the owner already stored on this property.
                        $current = $this->route('property') instanceof Property
                            ? $this->route('property')->owner_contact_id : null;
                        $q->where(fn ($w) => $w->where('owner_id', $user->id)
                            ->when($current, fn ($w) => $w->orWhere('id', $current)));
                    }
                }),
            ],
            'listing_agent_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return ['owner_contact_id.exists' => 'Pick one of your own contacts as the owner.'];
    }

    /** Validated fields; the controller decides the listing agent. */
    public function propertyData(): array
    {
        $data = $this->safe()->except('listing_agent_id');
        $data['features'] = array_values(array_unique($data['features'] ?? []));
        $data['is_published'] = (bool) ($data['is_published'] ?? false);
        $data['hide_exact_address'] = (bool) ($data['hide_exact_address'] ?? false);

        return $data;
    }
}
