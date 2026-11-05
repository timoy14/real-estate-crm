<?php

use App\Models\Contact;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function listed(array $attrs = []): Property
{
    return Property::factory()->create(array_merge(['is_published' => true, 'status' => 'available'], $attrs));
}

test('index lists only available + published properties', function () {
    $a = listed(['title' => 'Alpha House']);
    listed(['title' => 'Beta House']);

    $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('public/Listings/Index')
        ->has('listings.data', 2)
        ->where('meta.title', 'Homes for sale and rent | Skyline Realty')
        ->has('filters')
        ->has('areas'));
    expect($a->slug)->toBeString();
});

test('draft, sold and unpublished are not listed', function () {
    listed(['title' => 'Visible']);
    listed(['title' => 'Sold one', 'status' => 'sold']);
    listed(['title' => 'Draft one', 'status' => 'draft']);
    listed(['title' => 'Hidden one', 'is_published' => false]);
    Property::factory()->create(['title' => 'Trashed', 'is_published' => true])->delete();

    $this->get('/')->assertInertia(fn (Assert $p) => $p
        ->has('listings.data', 1)
        ->where('listings.data.0.title', 'Visible'));
});

test('show returns 404 for a sold property\'s slug', function () {
    $p = listed(['status' => 'sold']);
    $this->get("/properties/{$p->slug}")->assertNotFound();

    $q = listed(['is_published' => false]);
    $this->get("/properties/{$q->slug}")->assertNotFound();
});

test('show does not resolve a numeric id, only the slug', function () {
    $p = listed();
    $this->get("/properties/{$p->id}")->assertNotFound();
    $this->get("/properties/{$p->slug}")->assertOk();
});

test('serialized props never contain internal keys', function () {
    $owner = Contact::factory()->create();
    $p = listed(['owner_contact_id' => $owner->id]);
    $p->forceFill(['description' => 'Nice place'])->save();

    foreach (['/', "/properties/{$p->slug}"] as $url) {
        $json = html_entity_decode($this->get($url)->getContent());
        foreach (['owner', 'owner_contact_id', 'notes', 'listing_agent_id', 'status', 'is_published', 'hide_exact_address', 'deleted_at', 'user_id'] as $key) {
            expect($json)->not->toContain('"'.$key.'"');
        }
        expect($json)->not->toContain($owner->email ?? 'no-email-x');
    }

    $this->get("/properties/{$p->slug}")->assertInertia(fn (Assert $a) => $a
        ->component('public/Listings/Show')
        ->has('listing', fn (Assert $l) => $l
            ->hasAll(['slug', 'title', 'listing_type', 'property_type', 'price', 'bedrooms', 'bathrooms',
                'floor_area_sqft', 'lot_area_sqft', 'year_built', 'features', 'description', 'area', 'city',
                'address', 'latitude', 'longitude', 'photos', 'cover_url', 'agent'])
            ->has('agent', fn (Assert $ag) => $ag->hasAll(['name', 'avatar_url']))
            ->etc()));
});

test('hide_exact_address nulls address and coordinates', function () {
    $p = listed(['address' => '12 Secret St', 'latitude' => 25.7, 'longitude' => -80.2, 'hide_exact_address' => true]);

    $this->get("/properties/{$p->slug}")->assertInertia(fn (Assert $a) => $a
        ->where('listing.address', null)
        ->where('listing.latitude', null)
        ->where('listing.longitude', null)
        ->where('listing.area', $p->area));
    expect($this->get("/properties/{$p->slug}")->getContent())->not->toContain('Secret St');

    $q = listed(['address' => '5 Open Ave', 'latitude' => 25.7, 'longitude' => -80.2, 'hide_exact_address' => false]);
    $this->get("/properties/{$q->slug}")->assertInertia(fn (Assert $a) => $a
        ->where('listing.address', '5 Open Ave')->where('listing.latitude', 25.7));
});

test('property with no photos returns placeholder cover_url and empty photos', function () {
    $p = listed();
    $this->get("/properties/{$p->slug}")->assertInertia(fn (Assert $a) => $a
        ->where('listing.cover_url', '/images/placeholder-property.svg')
        ->where('listing.photos', []));
    $this->get('/')->assertInertia(fn (Assert $a) => $a
        ->where('listings.data.0.cover_url', '/images/placeholder-property.svg'));
});

test('photos are returned as urls in order', function () {
    $p = listed();
    PropertyPhoto::create(['property_id' => $p->id, 'path' => 'properties/1/b.jpg', 'position' => 2]);
    PropertyPhoto::create(['property_id' => $p->id, 'path' => 'properties/1/a.jpg', 'position' => 1]);

    $this->get("/properties/{$p->slug}")->assertInertia(fn (Assert $a) => $a
        ->has('listing.photos', 2)
        ->where('listing.photos.0', fn ($u) => str_ends_with($u, 'a.jpg')));
});

test('filter type=rent&beds=2 returns only matching', function () {
    listed(['title' => 'Rent 3', 'listing_type' => 'rent', 'bedrooms' => 3, 'price' => 2000]);
    listed(['title' => 'Rent 1', 'listing_type' => 'rent', 'bedrooms' => 1, 'price' => 1000]);
    listed(['title' => 'Sale 3', 'listing_type' => 'sale', 'bedrooms' => 3, 'price' => 300000]);

    $this->get('/?type=rent&beds=2')->assertInertia(fn (Assert $a) => $a
        ->has('listings.data', 1)
        ->where('listings.data.0.title', 'Rent 3')
        ->where('filters.type', 'rent'));
});

test('area, property_type and price filters work and junk values are ignored', function () {
    listed(['title' => 'In Brickell', 'area' => 'Brickell', 'property_type' => 'condo', 'price' => 500000, 'listing_type' => 'sale']);
    listed(['title' => 'In Wynwood', 'area' => 'Wynwood', 'property_type' => 'house', 'price' => 900000, 'listing_type' => 'sale']);

    $this->get('/?area=Brickell')->assertInertia(fn (Assert $a) => $a->has('listings.data', 1));
    $this->get('/?property_type=house')->assertInertia(fn (Assert $a) => $a->where('listings.data.0.title', 'In Wynwood'));
    $this->get('/?min_price=600000')->assertInertia(fn (Assert $a) => $a->has('listings.data', 1)->where('listings.data.0.title', 'In Wynwood'));
    $this->get('/?max_price=600000')->assertInertia(fn (Assert $a) => $a->has('listings.data', 1)->where('listings.data.0.title', 'In Brickell'));
    $this->get('/?type=bogus&beds=abc&sort=evil&property_type=zzz&min_price[]=1')->assertOk()
        ->assertInertia(fn (Assert $a) => $a->has('listings.data', 2));
});

test('areas lists distinct areas of listed properties only', function () {
    listed(['area' => 'Brickell']);
    listed(['area' => 'Brickell']);
    listed(['area' => 'Hidden Area', 'status' => 'sold']);

    $this->get('/')->assertInertia(fn (Assert $a) => $a->where('areas', ['Brickell']));
});

test('sort price_asc orders ascending', function () {
    listed(['title' => 'Mid', 'price' => 200000, 'listing_type' => 'sale']);
    listed(['title' => 'Low', 'price' => 100000, 'listing_type' => 'sale']);
    listed(['title' => 'High', 'price' => 300000, 'listing_type' => 'sale']);

    $this->get('/?sort=price_asc')->assertInertia(fn (Assert $a) => $a
        ->where('listings.data.0.title', 'Low')->where('listings.data.2.title', 'High'));
    $this->get('/?sort=price_desc')->assertInertia(fn (Assert $a) => $a
        ->where('listings.data.0.title', 'High'));
});

test('index paginates 12 and keeps the query string', function () {
    Property::factory()->count(14)->create(['is_published' => true, 'status' => 'available', 'listing_type' => 'sale']);

    $this->get('/?type=sale')->assertInertia(fn (Assert $a) => $a
        ->has('listings.data', 12)
        ->where('listings.links.next', fn ($u) => str_contains($u, 'type=sale') && str_contains($u, 'page=2')));
});

test('show HTML contains <title> and og:image server-rendered', function () {
    $agent = User::factory()->create(['name' => 'Anna Reyes']);
    $p = listed(['title' => 'Sunny Loft', 'price' => 450000, 'listing_type' => 'sale', 'listing_agent_id' => $agent->id,
        'description' => str_repeat('x', 300)]);
    $r = listed(['title' => 'Rental Flat', 'price' => 2500, 'listing_type' => 'rent']);

    $html = $this->get("/properties/{$p->slug}")->assertOk()->getContent();
    expect($html)
        ->toContain('<title>Sunny Loft – ₱450,000 | Skyline Realty</title>')
        ->toContain('<meta property="og:image" content="'.url('/images/placeholder-property.svg').'">')
        ->toContain('<meta property="og:url" content="'.url("/properties/{$p->slug}").'">');

    $this->get("/properties/{$p->slug}")->assertInertia(fn (Assert $a) => $a
        ->where('meta.description', str_repeat('x', 155)));

    expect($this->get("/properties/{$r->slug}")->getContent())->toContain('Rental Flat – ₱2,500/mo | Skyline Realty');
});

test('public routes need no login and expose only the agent name and avatar', function () {
    $agent = User::factory()->create(['name' => 'Anna Reyes', 'email' => 'anna-private@demo.test']);
    $p = listed(['listing_agent_id' => $agent->id]);
    $r = $this->get("/properties/{$p->slug}");
    $r->assertOk()->assertInertia(fn (Assert $a) => $a
        ->where('listing.agent', ['name' => 'Anna Reyes', 'avatar_url' => null]));
    expect($r->getContent())->not->toContain('anna-private@demo.test');
});

test('out-of-range numeric filters do not 500', function () {
    listed();
    $this->get('/?beds=99999')->assertOk();
    $this->get('/?beds=21&min_price=99999999999999&max_price=9999999999999999')->assertOk();
});
