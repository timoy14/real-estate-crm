<?php

namespace Database\Factories;

use App\Models\Inquiry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Inquiry> */
class InquiryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => null,
            'message' => fake()->sentence(),
            'wants_viewing' => false,
            'preferred_contact' => 'email',
            'consent' => true,
            'source' => 'website',
            'ip' => fake()->ipv4(),
            'user_agent' => 'Test',
            'status' => 'new',
            'owner_id' => User::factory(),
        ];
    }
}
