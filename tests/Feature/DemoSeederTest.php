<?php

use App\Enums\ActivityType;
use App\Enums\ViewingStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Inquiry;
use App\Models\Pipeline;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('demo seeder builds a consistent data set without any outbound requests', function () {
    Storage::fake('public');
    Http::preventStrayRequests();
    config(['crm.ai.enabled' => true]); // must be switched off while seeding and restored after

    $this->seed(DatabaseSeeder::class);

    expect(config('crm.ai.enabled'))->toBeTrue();
    expect(User::count())->toBe(3);
    expect(Pipeline::count())->toBe(2);
    expect(Property::count())->toBe(60);
    expect(Property::where('listing_type', 'sale')->count())->toBe(40);
    expect(Property::publiclyListed()->count())->toBe(50);
    expect(Property::where('hide_exact_address', true)->count())->toBe(6);
    expect(Contact::count())->toBe(120);
    expect(Contact::where('owner_id', User::where('email', 'anna@demo.test')->value('id'))->count())->toBe(50);

    $anna = User::where('email', 'anna@demo.test')->first();
    $today = now()->startOfDay();
    expect(Activity::where('type', ActivityType::Viewing->value)
        ->where('viewing_status', ViewingStatus::Scheduled->value)
        ->where('owner_id', $anna->id)
        ->whereBetween('scheduled_at', [$today, $today->copy()->endOfDay()])->count())->toBe(2);
    expect(Activity::where('viewing_status', ViewingStatus::Scheduled->value)->count())->toBe(10);
    expect(Activity::where('viewing_status', ViewingStatus::Completed->value)->count())->toBe(80);
    expect(Activity::count())->toBe(400);
    expect(Task::count())->toBe(100);
    expect(Inquiry::count())->toBe(25);
    expect(Inquiry::where('status', 'new')->count())->toBe(10);
    expect(Deal::count())->toBe(70);

    Deal::with('stage')->get()->each(function (Deal $deal) {
        expect($deal->stage->pipeline_id)->toBe($deal->pipeline_id);
        expect($deal->status->value)->toBe($deal->stage->is_won ? 'won' : ($deal->stage->is_lost ? 'lost' : 'open'));
        expect($deal->closed_at !== null)->toBe($deal->stage->is_won || $deal->stage->is_lost);
    });
    // Positions are contiguous 0..n-1 per stage column.
    Deal::all()->groupBy('stage_id')->each(fn ($g) => expect($g->pluck('position')->sort()->values()->all())->toBe(range(0, $g->count() - 1)));

    // Every inquiry has its contact, deal, task and note.
    Inquiry::all()->each(function (Inquiry $i) {
        expect($i->contact_id)->not->toBeNull()->and($i->deal_id)->not->toBeNull();
        expect(Task::where('inquiry_id', $i->id)->exists())->toBeTrue();
        expect(Activity::where('deal_id', $i->deal_id)->where('subject', 'Website inquiry')->exists())->toBeTrue();
    });

    Property::all()->each(function (Property $p) {
        expect(PropertyPhoto::where('property_id', $p->id)->count())->toBeGreaterThanOrEqual(1);
        expect(array_diff($p->features, Property::FEATURES))->toBe([]);
    });
    $photo = PropertyPhoto::first();
    Storage::disk('public')->assertExists($photo->path);
    expect($photo->path)->toStartWith('properties/'.$photo->property_id.'/');

    // Descriptions of land and commercial never mention bedrooms.
    Property::whereNull('bedrooms')->get()->each(function (Property $p) {
        expect(strtolower($p->description))->not->toContain('multi')->not->toContain('bedroom');
    });

    // Prices grow with bedrooms inside one listing type, property type and area; small units are capped at 3 beds.
    Property::whereNotNull('bedrooms')->get()->groupBy(fn ($p) => $p->listing_type->value.$p->property_type->value.$p->area)
        ->each(function ($group) {
            foreach ($group as $a) {
                foreach ($group as $b) {
                    if ($a->bedrooms < $b->bedrooms) {
                        expect($a->price)->toBeLessThanOrEqual($b->price);
                    }
                }
            }
        });
    expect(Property::whereIn('property_type', ['apartment', 'condo'])->where('bedrooms', '>', 3)->count())->toBe(0);
    Property::all()->each(fn ($p) => expect($p->price % ($p->listing_type->value === 'rent' ? 1000 : 100000))->toBe(0));

    // No placeholder fallbacks in activity text.
    expect(Activity::where('body', 'like', '%the property%')->orWhere('body', 'like', '%the client%')->count())->toBe(0);

    // Nothing on a property predates the property itself.
    Activity::whereNotNull('property_id')->with('property')->get()->each(function (Activity $a) {
        expect($a->occurred_at ?? $a->scheduled_at)->not->toBeNull();
        if ($a->occurred_at) {
            expect($a->occurred_at->gte($a->property->created_at))->toBeTrue();
        }
    });

    // Photo URLs are relative so they work on any host.
    expect($photo->url())->toBe('/storage/'.$photo->path);
});
