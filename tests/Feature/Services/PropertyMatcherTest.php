<?php

use App\Models\Contact;
use App\Models\Property;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\Matching\PropertyMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function wl(array $o = []): Wishlist
{
    return Wishlist::factory()->create(array_merge([
        'listing_type' => 'sale', 'property_types' => ['house'], 'min_budget' => 100000, 'max_budget' => 500000,
        'min_bedrooms' => null, 'min_bathrooms' => null, 'areas' => [], 'must_have_features' => [],
    ], $o));
}

function pr(array $o = []): Property
{
    return Property::factory()->create(array_merge([
        'listing_type' => 'sale', 'property_type' => 'house', 'price' => 300000, 'area' => 'Downtown',
        'bedrooms' => 3, 'bathrooms' => 2, 'features' => [], 'status' => 'available',
    ], $o));
}

it('excludes property over max budget', function () {
    pr(['price' => 600000]);
    expect(app(PropertyMatcher::class)->forWishlist(wl()))->toHaveCount(0);
});

it('null max_budget is open-ended', function () {
    pr(['price' => 9000000]);
    expect(app(PropertyMatcher::class)->forWishlist(wl(['max_budget' => null])))->toHaveCount(1);
});

it('excludes a rent listing for a sale wishlist', function () {
    pr(['listing_type' => 'rent']);
    expect(app(PropertyMatcher::class)->forWishlist(wl()))->toHaveCount(0);
});

it('excludes a non-available property', function () {
    pr(['status' => 'sold']);
    expect(app(PropertyMatcher::class)->forWishlist(wl()))->toHaveCount(0);
});

it('area match is case-insensitive', function () {
    pr(['area' => 'Downtown']);
    expect(app(PropertyMatcher::class)->forWishlist(wl(['areas' => ['downTOWN']])))->toHaveCount(1);
    expect(app(PropertyMatcher::class)->forWishlist(wl(['areas' => ['Brickell']])))->toHaveCount(0);
});

it('empty property_types matches any type', function () {
    pr(['property_type' => 'condo']);
    expect(app(PropertyMatcher::class)->forWishlist(wl(['property_types' => []])))->toHaveCount(1);
    expect(app(PropertyMatcher::class)->forWishlist(wl(['property_types' => ['house']])))->toHaveCount(0);
});

it('applies bedroom and bathroom minimums', function () {
    pr(['bedrooms' => 2, 'bathrooms' => 1]);
    expect(app(PropertyMatcher::class)->forWishlist(wl(['min_bedrooms' => 3])))->toHaveCount(0);
    expect(app(PropertyMatcher::class)->forWishlist(wl(['min_bathrooms' => 2])))->toHaveCount(0);
    expect(app(PropertyMatcher::class)->forWishlist(wl(['min_bedrooms' => 2, 'min_bathrooms' => 1])))->toHaveCount(1);
});

it('score is 100 when all must-haves are present, 80 when half, 60 when none', function () {
    $w = wl(['must_have_features' => ['pool', 'gym']]);
    pr(['features' => ['pool', 'gym', 'garden']]);
    pr(['features' => ['pool']]);
    pr(['features' => ['garden']]);
    $scores = app(PropertyMatcher::class)->forWishlist($w)->pluck('match_score')->all();
    expect($scores)->toBe([100, 80, 60]);
    expect(app(PropertyMatcher::class)->forWishlist(wl())->first()->match_score)->toBe(100);
});

it('results ordered by score desc', function () {
    $w = wl(['must_have_features' => ['pool']]);
    $low = pr(['features' => []]);
    $high = pr(['features' => ['pool']]);
    expect(app(PropertyMatcher::class)->forWishlist($w)->pluck('id')->all())->toBe([$high->id, $low->id]);
});

it('forProperty hides wishlists of contacts the viewer cannot see', function () {
    $anna = User::factory()->create();
    $ben = User::factory()->create();
    $mine = wl(['contact_id' => Contact::factory()->create(['owner_id' => $anna->id])->id]);
    wl(['contact_id' => Contact::factory()->create(['owner_id' => $ben->id])->id]);
    $res = app(PropertyMatcher::class)->forProperty(pr(), $anna);
    expect($res->pluck('id')->all())->toBe([$mine->id]);
    expect($res->first()->relationLoaded('contact'))->toBeTrue();
    expect($res->first()->match_score)->toBe(100);
});

it('forProperty filters on the property side', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    wl();
    expect(app(PropertyMatcher::class)->forProperty(pr(['price' => 900000]), $admin))->toHaveCount(0);
    expect(app(PropertyMatcher::class)->forProperty(pr(['status' => 'sold']), $admin))->toHaveCount(0);
    expect(app(PropertyMatcher::class)->forProperty(pr(['area' => 'X']), $admin))->toHaveCount(1);
});

it('wires matches into the client page and interestedClients into the property page', function () {
    $anna = User::factory()->create();
    $ben = User::factory()->create();
    $contact = Contact::factory()->create(['owner_id' => $anna->id]);
    $w = wl(['contact_id' => $contact->id]);
    wl(['contact_id' => Contact::factory()->create(['owner_id' => $ben->id])->id]);
    $p = pr();

    $this->actingAs($anna)->get(route('app.clients.show', $contact))
        ->assertInertia(fn ($page) => $page
            ->where('matches.0.wishlist_id', $w->id)
            ->where('matches.0.properties.0.id', $p->id)
            ->where('matches.0.properties.0.match_score', 100));

    $this->actingAs($anna)->get(route('app.properties.show', $p))
        ->assertInertia(fn ($page) => $page
            ->has('interestedClients', 1)
            ->where('interestedClients.0.wishlist_id', $w->id)
            ->where('interestedClients.0.contact.id', $contact->id)
            ->where('interestedClients.0.match_score', 100));
});
