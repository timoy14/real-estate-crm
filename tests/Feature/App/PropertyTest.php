<?php

use App\Enums\Role;
use App\Models\Contact;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->anna = User::factory()->create(['name' => 'Anna Reyes']);
    $this->ben = User::factory()->create(['name' => 'Ben Carter']);
});

function validProperty(array $overrides = []): array
{
    return array_merge([
        'title' => 'Sunny Loft Downtown',
        'address' => '12 Main St',
        'area' => 'Downtown',
        'city' => 'Miami',
        'listing_type' => 'sale',
        'property_type' => 'condo',
        'price' => 450000,
        'bedrooms' => 2,
        'bathrooms' => 2,
        'features' => ['pool', 'gym'],
        'description' => 'Bright corner unit.',
    ], $overrides);
}

test('any agent can list and view any property', function () {
    $p = Property::factory()->for($this->ben, 'listingAgent')->create();

    $this->actingAs($this->anna)->get('/app/properties')
        ->assertOk()
        ->assertInertia(fn (Assert $a) => $a->component('app/Properties/Index')->has('properties.data', 1));

    $this->actingAs($this->anna)->get("/app/properties/{$p->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $a) => $a->component('app/Properties/Show')
            ->where('property.id', $p->id)
            ->where('can.update', false));
});

test('non-listing agent gets 403 on update and delete', function () {
    $p = Property::factory()->for($this->ben, 'listingAgent')->create();

    $this->actingAs($this->anna)->patch("/app/properties/{$p->slug}", validProperty())->assertForbidden();
    $this->actingAs($this->anna)->delete("/app/properties/{$p->slug}")->assertForbidden();
    expect(Property::find($p->id))->not->toBeNull();

    $this->actingAs($this->ben)->patch("/app/properties/{$p->slug}", validProperty(['title' => 'New']))->assertRedirect();
    expect($p->fresh()->title)->toBe('New');
    $this->actingAs($this->admin)->delete("/app/properties/{$p->id}")->assertRedirect(); // numeric ids resolve too
    expect(Property::find($p->id))->toBeNull();
});

test('non-listing agent sees owner = null in show props; listing agent and admin see owner', function () {
    $seller = Contact::factory()->for($this->ben, 'owner')->create(['first_name' => 'Sally', 'last_name' => 'Seller']);
    $p = Property::factory()->for($this->ben, 'listingAgent')->create(['owner_contact_id' => $seller->id]);

    $this->actingAs($this->anna)->get("/app/properties/{$p->slug}")
        ->assertInertia(fn (Assert $a) => $a->where('owner', null)->where('can.viewOwnerDetails', false));

    foreach ([$this->ben, $this->admin] as $user) {
        $this->actingAs($user)->get("/app/properties/{$p->slug}")
            ->assertInertia(fn (Assert $a) => $a->where('owner.id', $seller->id)
                ->where('owner.name', 'Sally Seller')->where('can.viewOwnerDetails', true));
    }
});

test('index payload never includes owner_contact_id for a non-listing agent', function () {
    $seller = Contact::factory()->for($this->ben, 'owner')->create();
    Property::factory()->for($this->ben, 'listingAgent')->create(['owner_contact_id' => $seller->id]);

    $res = $this->actingAs($this->anna)->get('/app/properties')->assertOk();
    expect($res->getContent())->not->toContain('owner_contact_id');

    $this->actingAs($this->anna)->get('/app/properties')
        ->assertInertia(fn (Assert $a) => $a->missing('properties.data.0.owner_contact_id')->missing('properties.data.0.owner'));
});

test('slug is title-id', function () {
    $res = $this->actingAs($this->anna)->post('/app/properties', validProperty());
    $p = Property::firstOrFail();
    expect($p->slug)->toBe('sunny-loft-downtown-'.$p->id)
        ->and($p->listing_agent_id)->toBe($this->anna->id);
    $res->assertRedirect("/app/properties/{$p->slug}");
});

test('admin may choose the listing agent, agents may not', function () {
    $this->actingAs($this->admin)->post('/app/properties', validProperty(['listing_agent_id' => $this->ben->id]));
    $this->actingAs($this->anna)->post('/app/properties', validProperty(['title' => 'Two', 'listing_agent_id' => $this->ben->id]));

    expect(Property::where('title', 'Sunny Loft Downtown')->first()->listing_agent_id)->toBe($this->ben->id)
        ->and(Property::where('title', 'Two')->first()->listing_agent_id)->toBe($this->anna->id);
});

test('filters min_bedrooms=3 & listing_type=sale return only matching', function () {
    Property::factory()->for($this->anna, 'listingAgent')->create(['bedrooms' => 3, 'listing_type' => 'sale', 'title' => 'Match']);
    Property::factory()->for($this->anna, 'listingAgent')->create(['bedrooms' => 2, 'listing_type' => 'sale']);
    Property::factory()->for($this->anna, 'listingAgent')->create(['bedrooms' => 4, 'listing_type' => 'rent']);

    $this->actingAs($this->anna)->get('/app/properties?min_bedrooms=3&listing_type=sale')
        ->assertInertia(fn (Assert $a) => $a->has('properties.data', 1)->where('properties.data.0.title', 'Match'));
});

test('other filters: price, search, status, area', function () {
    Property::factory()->for($this->anna, 'listingAgent')->create(['title' => 'Cheap', 'price' => 100000, 'area' => 'Coral', 'address' => '1 Oak', 'status' => 'available']);
    Property::factory()->for($this->anna, 'listingAgent')->create(['title' => 'Pricey', 'price' => 900000, 'area' => 'Brickell', 'address' => '2 Elm', 'status' => 'sold']);

    $get = fn (string $q) => $this->actingAs($this->anna)->get('/app/properties?'.$q);
    $get('min_price=500000')->assertInertia(fn (Assert $a) => $a->has('properties.data', 1)->where('properties.data.0.title', 'Pricey'));
    $get('max_price=500000')->assertInertia(fn (Assert $a) => $a->has('properties.data', 1)->where('properties.data.0.title', 'Cheap'));
    $get('search=elm')->assertInertia(fn (Assert $a) => $a->has('properties.data', 1)->where('properties.data.0.title', 'Pricey'));
    $get('status=available')->assertInertia(fn (Assert $a) => $a->has('properties.data', 1)->where('properties.data.0.title', 'Cheap'));
    $get('area=Brickell')->assertInertia(fn (Assert $a) => $a->has('properties.data', 1)->where('properties.data.0.title', 'Pricey'));
});

test('422 when owner_contact_id belongs to another agent\'s contact', function () {
    $bens = Contact::factory()->for($this->ben, 'owner')->create();

    $this->actingAs($this->anna)->post('/app/properties', validProperty(['owner_contact_id' => $bens->id]))
        ->assertSessionHasErrors('owner_contact_id');
    expect(Property::count())->toBe(0);

    $mine = Contact::factory()->for($this->anna, 'owner')->create();
    $this->actingAs($this->anna)->post('/app/properties', validProperty(['owner_contact_id' => $mine->id]))->assertSessionHasNoErrors();
    expect(Property::first()->owner_contact_id)->toBe($mine->id);
});

test('publicly listed scope and cover url', function () {
    Property::factory()->for($this->anna, 'listingAgent')->create(['status' => 'available', 'is_published' => true]);
    Property::factory()->for($this->anna, 'listingAgent')->create(['status' => 'available', 'is_published' => false]);
    Property::factory()->for($this->anna, 'listingAgent')->create(['status' => 'sold', 'is_published' => true]);

    expect(Property::publiclyListed()->count())->toBe(1)
        ->and(Property::first()->coverUrl())->toBe('/images/placeholder-property.svg');
});

test('deleting a contact nulls owner_contact_id; deleting a listing agent is restricted', function () {
    $c = Contact::factory()->for($this->ben, 'owner')->create();
    $p = Property::factory()->for($this->ben, 'listingAgent')->create(['owner_contact_id' => $c->id]);
    $c->forceDelete();
    expect($p->fresh()->owner_contact_id)->toBeNull();
    expect(fn () => $this->ben->forceDelete())->toThrow(QueryException::class);
});

test('routes require login', function () {
    $this->get('/app/properties')->assertRedirect('/login');
});

test('listing agent can save a property whose owner contact is not theirs, and the form lists it', function () {
    $sellers = Contact::factory()->for($this->ben, 'owner')->create();
    $p = Property::factory()->for($this->anna, 'listingAgent')->create(['owner_contact_id' => $sellers->id]);

    $this->actingAs($this->anna)->patch("/app/properties/{$p->id}", validProperty(['owner_contact_id' => $sellers->id]))
        ->assertSessionHasNoErrors();
    expect($p->fresh()->owner_contact_id)->toBe($sellers->id);

    $other = Contact::factory()->for($this->ben, 'owner')->create();
    $this->actingAs($this->anna)->patch("/app/properties/{$p->id}", validProperty(['owner_contact_id' => $other->id]))
        ->assertSessionHasErrors('owner_contact_id');

    $this->actingAs($this->anna)->get("/app/properties/{$p->id}")
        ->assertInertia(fn (Assert $a) => $a->where('contacts', fn ($c) => collect($c)->pluck('id')->contains($sellers->id)));
});

// --- Final review fixes (minor: humanized enum text) ---

test('property show sends a human label for each inquiry status', function () {
    $p = Property::factory()->for($this->anna, 'listingAgent')->create();
    \App\Models\Inquiry::factory()->create(['property_id' => $p->id, 'owner_id' => $this->anna->id, 'status' => 'contacted']);

    $this->actingAs($this->anna)->get("/app/properties/{$p->slug}")
        ->assertInertia(fn (Assert $page) => $page->where('inquiries.0.status', 'contacted')->where('inquiries.0.status_label', 'Contacted'));
});
