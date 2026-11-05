<?php

namespace Database\Factories;

use App\Enums\ListingType;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Needs the pipelines seeded (PipelineSeeder). By default a deal sits in the
 * first stage of the sales pipeline and its contact belongs to the same owner.
 *
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    public function definition(): array
    {
        $pipeline = Pipeline::forListingType(ListingType::Sale);
        $owner = User::factory()->create();

        return [
            'title' => fake()->words(3, true),
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->inquiryStage()->id,
            'position' => 0,
            'contact_id' => Contact::factory()->state(['owner_id' => $owner->id]),
            'property_id' => null,
            'value' => fake()->numberBetween(100, 900) * 1000,
            'commission_percent' => 3,
            'expected_close_date' => fake()->dateTimeBetween('+1 week', '+4 months')->format('Y-m-d'),
            'owner_id' => $owner->id,
        ];
    }

    /** Put the deal in a named stage of the given pipeline. */
    public function inStage(string $name, ListingType $type = ListingType::Sale): static
    {
        $pipeline = Pipeline::forListingType($type);

        return $this->state(fn () => [
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages()->where('name', $name)->firstOrFail()->id,
        ]);
    }
}
