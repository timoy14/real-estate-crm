<?php

namespace Database\Factories;

use App\Enums\ContactRole;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('(###) ###-####'),
            'roles' => [fake()->randomElement(ContactRole::cases())->value],
            'status' => fake()->randomElement(ContactStatus::cases())->value,
            'source' => fake()->randomElement(ContactSource::cases())->value,
            'tags' => [],
            'owner_id' => User::factory(),
        ];
    }
}
