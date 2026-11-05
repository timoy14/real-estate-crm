<?php

use App\Enums\Role;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Property;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\Assistant\AssistantTools;
use App\Services\Knowledge\Embedder;
use App\Services\Knowledge\FakeEmbedder;
use Database\Seeders\PipelineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PipelineSeeder::class);
    app()->bind(Embedder::class, FakeEmbedder::class);
    $this->anna = User::factory()->create();
    $this->ben = User::factory()->create();
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->tools = app(AssistantTools::class);
});

function run(string $name, array $input, User $user): array
{
    return json_decode(app(AssistantTools::class)->execute($name, $input, $user), true);
}

it('every definition has additionalProperties false and all properties required', function () {
    $defs = app(AssistantTools::class)->definitions();
    expect($defs)->toHaveCount(6);
    foreach ($defs as $d) {
        expect($d)->toHaveKeys(['name', 'description', 'input_schema'])
            ->and($d['input_schema']['type'])->toBe('object')
            ->and($d['input_schema']['additionalProperties'])->toBeFalse()
            ->and($d['input_schema']['required'])->toEqualCanonicalizing(array_keys($d['input_schema']['properties']));
    }
});

it('get_record on another agents contact returns error not found', function () {
    $c = Contact::factory()->create(['owner_id' => $this->ben->id, 'email' => 'b@x.test']);

    expect(run('get_record', ['type' => 'contact', 'id' => $c->id], $this->anna))->toBe(['error' => 'not found']);
    expect(run('get_record', ['type' => 'contact', 'id' => $c->id], $this->ben)['email'])->toBe('b@x.test');
});

it('get_record on another agents deal returns not found', function () {
    $deal = Deal::factory()->create(['owner_id' => $this->ben->id]);

    expect(run('get_record', ['type' => 'deal', 'id' => $deal->id], $this->anna))->toBe(['error' => 'not found']);
});

it('get_record property hides the owner for a non-listing agent', function () {
    $seller = Contact::factory()->create(['first_name' => 'Zebediah', 'email' => 'zeb@secret.test']);
    $p = Property::factory()->create(['listing_agent_id' => $this->ben->id, 'owner_contact_id' => $seller->id]);

    $annaJson = app(AssistantTools::class)->execute('get_record', ['type' => 'property', 'id' => $p->id], $this->anna);
    expect($annaJson)->not->toContain('Zebediah')->not->toContain('zeb@secret.test')->not->toContain('owner');

    $benJson = app(AssistantTools::class)->execute('get_record', ['type' => 'property', 'id' => $p->id], $this->ben);
    expect($benJson)->toContain('Zebediah');
    expect(app(AssistantTools::class)->execute('get_record', ['type' => 'property', 'id' => $p->id], $this->admin))->toContain('Zebediah');
});

it('matching_properties_for_client on Bens client returns not found for Anna', function () {
    $c = Contact::factory()->create(['owner_id' => $this->ben->id]);
    Wishlist::factory()->create(['contact_id' => $c->id]);

    expect(run('matching_properties_for_client', ['contact_id' => $c->id], $this->anna))->toBe(['error' => 'not found']);
});

it('matching_properties_for_client returns matches for the owner', function () {
    $c = Contact::factory()->create(['owner_id' => $this->anna->id]);
    Wishlist::factory()->create(['contact_id' => $c->id, 'listing_type' => 'sale', 'property_types' => ['house'],
        'min_budget' => null, 'max_budget' => 500000, 'min_bedrooms' => 2, 'areas' => []]);
    $p = Property::factory()->create(['listing_type' => 'sale', 'property_type' => 'house', 'price' => 400000, 'bedrooms' => 3]);

    $res = run('matching_properties_for_client', ['contact_id' => $c->id], $this->anna);

    expect(collect($res['matches'])->pluck('ref'))->toContain("property:{$p->id}");
});

it('matching_clients_for_property only returns visible clients', function () {
    $p = Property::factory()->create(['listing_type' => 'sale', 'property_type' => 'house', 'price' => 400000, 'bedrooms' => 3, 'area' => 'Downtown']);
    $mine = Contact::factory()->create(['owner_id' => $this->anna->id]);
    $theirs = Contact::factory()->create(['owner_id' => $this->ben->id]);
    foreach ([$mine, $theirs] as $c) {
        Wishlist::factory()->create(['contact_id' => $c->id, 'listing_type' => 'sale', 'property_types' => [],
            'min_budget' => null, 'max_budget' => null, 'min_bedrooms' => null, 'min_bathrooms' => null, 'areas' => []]);
    }

    $refs = collect(run('matching_clients_for_property', ['property_id' => $p->id], $this->anna)['matches'])->pluck('ref');

    expect($refs)->toContain("contact:{$mine->id}")->not->toContain("contact:{$theirs->id}");
});

it('list_deals idle_days=14 returns a deal with last activity 20 days ago, not one with activity 2 days ago', function () {
    $idle = Deal::factory()->create(['owner_id' => $this->anna->id, 'title' => 'Idle deal']);
    $busy = Deal::factory()->create(['owner_id' => $this->anna->id, 'title' => 'Busy deal']);
    Activity::factory()->create(['owner_id' => $this->anna->id, 'deal_id' => $idle->id, 'contact_id' => $idle->contact_id, 'occurred_at' => now()->subDays(20)]);
    Activity::factory()->create(['owner_id' => $this->anna->id, 'deal_id' => $busy->id, 'contact_id' => $busy->contact_id, 'occurred_at' => now()->subDays(2)]);

    $titles = collect(run('list_deals', ['idle_days' => 14], $this->anna)['deals'])->pluck('title');

    expect($titles)->toContain('Idle deal')->not->toContain('Busy deal');
});

it('list_deals counts a deal with no activity by created_at', function () {
    $old = Deal::factory()->create(['owner_id' => $this->anna->id, 'title' => 'Old quiet']);
    $old->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();
    Deal::factory()->create(['owner_id' => $this->anna->id, 'title' => 'New quiet']);

    $titles = collect(run('list_deals', ['idle_days' => 14], $this->anna)['deals'])->pluck('title');

    expect($titles)->toContain('Old quiet')->not->toContain('New quiet');
});

it('list_deals for Anna never includes Bens deals', function () {
    Deal::factory()->create(['owner_id' => $this->anna->id, 'title' => 'Annas deal']);
    Deal::factory()->create(['owner_id' => $this->ben->id, 'title' => 'Bens deal']);

    $titles = collect(run('list_deals', [], $this->anna)['deals'])->pluck('title');

    expect($titles)->toContain('Annas deal')->not->toContain('Bens deal');
    expect(collect(run('list_deals', [], $this->admin)['deals'])->pluck('title'))->toContain('Annas deal', 'Bens deal');
});

it('list_deals filters by status, pipeline, closing window and clamps days', function () {
    Deal::factory()->create(['owner_id' => $this->anna->id, 'title' => 'Soon', 'expected_close_date' => now()->addDays(5)->toDateString()]);
    Deal::factory()->create(['owner_id' => $this->anna->id, 'title' => 'Later', 'expected_close_date' => now()->addDays(90)->toDateString()]);

    $titles = collect(run('list_deals', ['status' => 'open', 'pipeline' => 'sales', 'closing_within_days' => 30], $this->anna)['deals'])->pluck('title');
    expect($titles)->toContain('Soon')->not->toContain('Later');

    expect(run('list_deals', ['pipeline' => 'rentals'], $this->anna)['deals'])->toBe([]);
    expect(run('list_deals', ['idle_days' => 100000], $this->anna))->toHaveKey('deals');
    expect(run('list_deals', ['status' => 'bogus'], $this->anna))->toHaveKey('error');
});

it('search_properties filters price and bedrooms', function () {
    $ok = Property::factory()->create(['title' => 'Fits', 'listing_type' => 'sale', 'price' => 400000, 'bedrooms' => 3]);
    Property::factory()->create(['title' => 'Too pricey', 'listing_type' => 'sale', 'price' => 900000, 'bedrooms' => 3]);
    Property::factory()->create(['title' => 'Too small', 'listing_type' => 'sale', 'price' => 400000, 'bedrooms' => 1]);
    Property::factory()->create(['title' => 'Sold one', 'listing_type' => 'sale', 'price' => 400000, 'bedrooms' => 3, 'status' => 'sold']);

    $res = run('search_properties', ['max_price' => 500000, 'min_bedrooms' => 2, 'listing_type' => 'sale'], $this->anna);

    expect(collect($res['properties'])->pluck('title')->all())->toBe(['Fits'])
        ->and($res['properties'][0]['ref'])->toBe("property:{$ok->id}");
});

it('search_properties treats a wildcard area as literal text', function () {
    Property::factory()->create(['area' => 'Downtown']);

    expect(run('search_properties', ['area' => '%'], $this->anna)['properties'])->toBe([]);
});

it('search_crm_notes never returns another agents chunks', function () {
    $c = Contact::factory()->create(['owner_id' => $this->ben->id]);
    Activity::factory()->create(['owner_id' => $this->ben->id, 'contact_id' => $c->id, 'subject' => 'Kitchen', 'body' => 'wants a kitchen island']);
    config(['crm.ai.enabled' => true]);
    Activity::factory()->create(['owner_id' => $this->ben->id, 'contact_id' => $c->id, 'subject' => 'Kitchen2', 'body' => 'wants a kitchen island']);

    expect(run('search_crm_notes', ['query' => 'kitchen'], $this->anna)['results'])->toBe([]);
    $res = run('search_crm_notes', ['query' => 'kitchen'], $this->ben)['results'];
    expect($res)->not->toBeEmpty()->and($res[0])->toHaveKeys(['ref', 'excerpt', 'date']);
});

it('unknown tool returns error json', function () {
    expect(run('drop_tables', [], $this->anna))->toHaveKey('error');
});

it('bad input type returns error json', function () {
    expect(run('get_record', ['type' => 'contact', 'id' => 'abc'], $this->anna))->toHaveKey('error')
        ->and(run('get_record', ['type' => 'users', 'id' => 1], $this->anna))->toHaveKey('error')
        ->and(run('search_properties', ['min_price' => 'cheap'], $this->anna))->toHaveKey('error')
        ->and(run('search_crm_notes', ['query' => ['x']], $this->anna))->toHaveKey('error')
        ->and(run('list_deals', ['idle_days' => 'x'], $this->anna))->toHaveKey('error');
});

it('ids below 1 are rejected rather than clamped to 1', function () {
    $c = Contact::factory()->create(['owner_id' => $this->anna->id]);
    Contact::query()->whereKey($c->id)->update(['id' => 1]);
    foreach ([0, -5] as $bad) {
        expect(run('get_record', ['type' => 'contact', 'id' => $bad], $this->anna))->toHaveKey('error')
            ->and(run('matching_properties_for_client', ['contact_id' => $bad], $this->anna))->toHaveKey('error');
    }
});
