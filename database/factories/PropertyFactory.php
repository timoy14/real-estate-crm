<?php

namespace Database\Factories;

use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        $listing = fake()->randomElement(ListingType::cases());

        return [
            'title' => fake()->words(3, true),
            'address' => fake()->streetAddress(),
            'area' => fake()->randomElement(['Downtown', 'Brickell', 'Coral Gables', 'Wynwood']),
            'city' => 'Miami',
            'listing_type' => $listing->value,
            'property_type' => fake()->randomElement(PropertyType::cases())->value,
            'price' => $listing === ListingType::Sale ? fake()->numberBetween(150, 1500) * 1000 : fake()->numberBetween(8, 60) * 100,
            'bedrooms' => fake()->numberBetween(1, 5),
            'bathrooms' => fake()->numberBetween(1, 4),
            'floor_area_sqft' => fake()->numberBetween(500, 4000),
            'features' => fake()->randomElements(Property::FEATURES, 3),
            'description' => fake()->paragraph(),
            'status' => PropertyStatus::Available->value,
            'is_published' => false,
            'hide_exact_address' => false,
            'listing_agent_id' => User::factory(),
        ];
    }
}
