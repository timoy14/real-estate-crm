<?php

use App\Enums\Role;
use App\Models\Contact;
use App\Models\Property;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\Matching\PropertyMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->anna = User::factory()->create();
    $this->ben = User::factory()->create();
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

function wishlistPayload(array $o = []): array
{
    return array_merge([
        'listing_type' => 'sale',
        'property_types' => ['house', 'condo'],
        'min_budget' => 300000,
        'max_budget' => 500000,
        'min_bedrooms' => 3,
        'areas' => ['Brooklyn'],
        'must_have_features' => ['garage'],
    ], $o);
}

test('agent can add a wishlist to own client', function () {
    $c = Contact::factory()->for($this->anna, 'owner')->create();

    $this->actingAs($this->anna)->post("/app/clients/{$c->id}/wishlists", wishlistPayload())
        ->assertRedirect();

    $w = $c->wishlists()->firstOrFail();
    expect($w->max_budget)->toBe(500000)->and($w->areas)->toBe(['Brooklyn']);
});

test('403 on another agent\'s client', function () {
    $c = Contact::factory()->for($this->ben, 'owner')->create();
    $w = Wishlist::factory()->for($c)->create();

    $this->actingAs($this->anna)->post("/app/clients/{$c->id}/wishlists", wishlistPayload())->assertForbidden();
    $this->actingAs($this->anna)->patch("/app/wishlists/{$w->id}", wishlistPayload())->assertForbidden();
    $this->actingAs($this->anna)->delete("/app/wishlists/{$w->id}")->assertForbidden();
    expect(Wishlist::count())->toBe(1);
});

test('max_budget must be >= min_budget', function () {
    $c = Contact::factory()->for($this->anna, 'owner')->create();

    $this->actingAs($this->anna)->post("/app/clients/{$c->id}/wishlists", wishlistPayload(['min_budget' => 600000]))
        ->assertSessionHasErrors('max_budget');
    expect(Wishlist::count())->toBe(0);
});

test('listing_type is required', function () {
    $c = Contact::factory()->for($this->anna, 'owner')->create();
    $this->actingAs($this->anna)->post("/app/clients/{$c->id}/wishlists", wishlistPayload(['listing_type' => null]))
        ->assertSessionHasErrors('listing_type');
});

test('update and destroy own wishlist; show lists it', function () {
    $c = Contact::factory()->for($this->anna, 'owner')->create();
    $w = Wishlist::factory()->for($c)->create();

    $this->actingAs($this->anna)->patch("/app/wishlists/{$w->id}", wishlistPayload(['max_budget' => 900000]))
        ->assertRedirect();
    expect($w->fresh()->max_budget)->toBe(900000);

    $this->actingAs($this->anna)->get("/app/clients/{$c->id}")
        ->assertInertia(fn ($p) => $p->has('wishlists', 1));

    $this->actingAs($this->anna)->delete("/app/wishlists/{$w->id}")->assertRedirect();
    expect(Wishlist::count())->toBe(0)->and(Wishlist::withTrashed()->count())->toBe(1);
});

test('admin can add a wishlist to any client', function () {
    $c = Contact::factory()->for($this->ben, 'owner')->create();
    $this->actingAs($this->admin)->post("/app/clients/{$c->id}/wishlists", wishlistPayload())->assertRedirect();
    expect($c->wishlists()->count())->toBe(1);
});

test('must_have_features must come from the feature vocabulary and a UI payload scores 100', function () {
    $c = Contact::factory()->for($this->anna, 'owner')->create();

    $this->actingAs($this->anna)->post("/app/clients/{$c->id}/wishlists", wishlistPayload(['must_have_features' => ['Pool']]))
        ->assertSessionHasErrors('must_have_features.0');

    $this->actingAs($this->anna)->post("/app/clients/{$c->id}/wishlists", wishlistPayload([
        'min_bedrooms' => null, 'areas' => [], 'must_have_features' => Property::FEATURES,
    ]))->assertSessionHasNoErrors();

    $prop = Property::factory()->create([
        'listing_type' => 'sale', 'property_type' => 'house', 'price' => 400000, 'status' => 'available',
        'features' => Property::FEATURES,
    ]);
    $w = $c->wishlists()->firstOrFail();
    $hit = app(PropertyMatcher::class)->forWishlist($w)->firstWhere('id', $prop->id);
    expect($hit->match_score)->toBe(100);
});

// --- Final review fixes (minor: success toasts) ---

test('saving a wishlist flashes a success toast', function () {
    $c = Contact::factory()->for($this->anna, 'owner')->create();
    $this->actingAs($this->anna)->post("/app/clients/{$c->id}/wishlists", wishlistPayload())
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Wishlist saved.']);
    $w = Wishlist::firstOrFail();
    $this->actingAs($this->anna)->patch("/app/wishlists/{$w->id}", wishlistPayload())
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Wishlist saved.']);
});
