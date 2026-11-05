<?php

namespace Database\Seeders;

use App\Enums\ListingType;
use App\Models\Pipeline;
use Illuminate\Database\Seeder;

class PipelineSeeder extends Seeder
{
    /** Safe to re-run: pipelines and stages are matched by slug / name. */
    public function run(): void
    {
        $definitions = [
            ['Sales', 'sales', ListingType::Sale, [
                ['Inquiry', 10], ['Viewing', 25], ['Offer', 50], ['Negotiation', 75],
                ['Closed Won', 100, 'won'], ['Lost', 0, 'lost'],
            ]],
            ['Rentals', 'rentals', ListingType::Rent, [
                ['Inquiry', 10], ['Viewing', 30], ['Application', 60],
                ['Lease Signed', 100, 'won'], ['Lost', 0, 'lost'],
            ]],
        ];

        foreach ($definitions as [$name, $slug, $type, $stages]) {
            $pipeline = Pipeline::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'listing_type' => $type],
            );

            foreach ($stages as $i => $stage) {
                $pipeline->stages()->updateOrCreate(
                    ['name' => $stage[0]],
                    [
                        'position' => $i,
                        'probability' => $stage[1],
                        'is_won' => ($stage[2] ?? null) === 'won',
                        'is_lost' => ($stage[2] ?? null) === 'lost',
                    ],
                );
            }
        }
    }
}
