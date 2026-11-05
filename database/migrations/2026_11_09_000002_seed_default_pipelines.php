<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The deals board and the public inquiry form need both default pipelines.
 * Idempotent: rows are matched by slug / (pipeline, stage name), so it is
 * safe on a database PipelineSeeder already filled (and the seeder stays safe after it).
 */
return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            ['Sales', 'sales', 'sale', [
                ['Inquiry', 10], ['Viewing', 25], ['Offer', 50], ['Negotiation', 75],
                ['Closed Won', 100, 'won'], ['Lost', 0, 'lost'],
            ]],
            ['Rentals', 'rentals', 'rent', [
                ['Inquiry', 10], ['Viewing', 30], ['Application', 60],
                ['Lease Signed', 100, 'won'], ['Lost', 0, 'lost'],
            ]],
        ];
        $now = now();

        foreach ($definitions as [$name, $slug, $type, $stages]) {
            $pipelineId = DB::table('pipelines')->where('slug', $slug)->value('id')
                ?? DB::table('pipelines')->insertGetId([
                    'name' => $name, 'slug' => $slug, 'listing_type' => $type,
                    'created_at' => $now, 'updated_at' => $now,
                ]);

            foreach ($stages as $i => $stage) {
                if (DB::table('stages')->where('pipeline_id', $pipelineId)->where('name', $stage[0])->exists()) {
                    continue;
                }
                DB::table('stages')->insert([
                    'pipeline_id' => $pipelineId,
                    'name' => $stage[0],
                    'position' => $i,
                    'probability' => $stage[1],
                    'is_won' => ($stage[2] ?? null) === 'won',
                    'is_lost' => ($stage[2] ?? null) === 'lost',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Pipelines may already hold deals; leave them in place.
    }
};
