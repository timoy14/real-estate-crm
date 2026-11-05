<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Enums\ViewingStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Activity> */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        $author = User::factory()->create();

        return [
            'type' => ActivityType::Note->value,
            'subject' => fake()->sentence(3),
            'body' => fake()->sentence(),
            'occurred_at' => now(),
            'owner_id' => $author->id,
            'contact_id' => Contact::factory()->state(['owner_id' => $author->id]),
        ];
    }

    /** A viewing that is booked but has not happened yet. */
    public function viewing(): static
    {
        return $this->state(fn () => [
            'type' => ActivityType::Viewing->value,
            'subject' => null,
            'occurred_at' => null,
            'scheduled_at' => now()->addDays(2),
            'viewing_status' => ViewingStatus::Scheduled->value,
            'property_id' => Property::factory(),
        ]);
    }
}
