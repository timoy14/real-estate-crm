<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('runs on postgres with pgvector', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');

    expect(DB::table('pg_extension')->where('extname', 'vector')->count())->toBe(1);
});

// --- Final review fixes (B-M5, B-M7) ---

it('indexes the foreign keys and filters used by lists and matching', function (string $table, array $columns) {
    expect(\Illuminate\Support\Facades\Schema::hasIndex($table, $columns))->toBeTrue();
})->with([
    ['deals', ['contact_id']],
    ['deals', ['property_id']],
    ['deals', ['pipeline_id']],
    ['wishlists', ['contact_id']],
    ['inquiries', ['contact_id']],
    ['inquiries', ['deal_id']],
    ['properties', ['listing_agent_id']],
    ['tasks', ['inquiry_id']],
    ['activities', ['type', 'viewing_status', 'scheduled_at']],
]);

it('migrations create both default pipelines and their stages, and the seeder does not duplicate them', function () {
    expect(\App\Models\Pipeline::query()->orderBy('id')->pluck('slug')->all())->toBe(['sales', 'rentals'])
        ->and(\App\Models\Stage::count())->toBe(11);

    $this->seed(\Database\Seeders\PipelineSeeder::class);
    expect(\App\Models\Pipeline::count())->toBe(2)->and(\App\Models\Stage::count())->toBe(11);
});

it('the deals board renders on a freshly migrated database without seeding', function () {
    $this->actingAs(\App\Models\User::factory()->create())->get('/app/deals')->assertOk();
});
