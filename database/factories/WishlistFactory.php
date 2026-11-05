<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Wishlist> */
class WishlistFactory extends Factory
{
    public function definition(): array
    {
        $min = fake()->numberBetween(200, 500) * 1000;

        return [
            'contact_id' => Contact::factory(),
            'listing_type' => 'sale',
            'property_types' => ['house'],
            'min_budget' => $min,
            'max_budget' => $min + 200000,
            'min_bedrooms' => 2,
            'min_bathrooms' => 1,
            'areas' => ['Downtown'],
            'must_have_features' => [],
        ];
    }
}
