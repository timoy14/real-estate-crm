<?php

use App\Enums\ContactSource;
use App\Enums\ListingType;
use App\Models\Contact;
use App\Models\User;
use App\Services\Inquiries\ContactResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->anna = User::factory()->create();
    $this->ben = User::factory()->create();
    $this->resolver = new ContactResolver;
});

test('Maria@X.com reuses the contact stored as maria@x.com', function () {
    $c = Contact::factory()->for($this->ben, 'owner')->create(['email' => 'maria@x.com', 'phone' => null]);

    $found = $this->resolver->resolve('Maria Lopez', 'Maria@X.com', null, ListingType::Sale, $this->anna);

    expect($found->id)->toBe($c->id)->and(Contact::count())->toBe(1);
});

test('(555) 123-4567 reuses 555-123-4567', function () {
    $c = Contact::factory()->for($this->ben, 'owner')->create(['email' => null, 'phone' => '555-123-4567']);

    $found = $this->resolver->resolve('Maria', null, '(555) 123-4567', ListingType::Sale, $this->anna);

    expect($found->id)->toBe($c->id)->and(Contact::count())->toBe(1);
});

test('email match wins over phone match', function () {
    $byEmail = Contact::factory()->for($this->ben, 'owner')->create(['email' => 'maria@x.com', 'phone' => null]);
    Contact::factory()->for($this->anna, 'owner')->create(['email' => 'other@x.com', 'phone' => '555-123-4567']);

    $found = $this->resolver->resolve('Maria', 'maria@x.com', '555-123-4567', ListingType::Sale, $this->anna);

    expect($found->id)->toBe($byEmail->id);
});

test('new contact for a rent listing gets role tenant', function () {
    $c = $this->resolver->resolve('Maria de la Cruz', 'm@x.com', '555 000 1111', ListingType::Rent, $this->anna);

    expect($c->roles->map->value->all())->toBe(['tenant'])
        ->and($c->first_name)->toBe('Maria')
        ->and($c->last_name)->toBe('de la Cruz')
        ->and($c->source)->toBe(ContactSource::Website)
        ->and($c->status->value)->toBe('new_lead')
        ->and($c->owner_id)->toBe($this->anna->id);
});

test('new contact for a sale listing gets role buyer', function () {
    $c = $this->resolver->resolve('Maria', 'm@x.com', null, ListingType::Sale, $this->anna);

    expect($c->roles->map->value->all())->toBe(['buyer'])->and($c->last_name)->toBe('');
});

test('reused seller gains role buyer and keeps owner', function () {
    $c = Contact::factory()->for($this->ben, 'owner')->create(['email' => 'maria@x.com', 'phone' => null, 'roles' => ['seller']]);

    $found = $this->resolver->resolve('Maria', 'maria@x.com', '555-111-2222', ListingType::Sale, $this->anna);

    expect($found->id)->toBe($c->id)
        ->and($found->fresh()->roles->map->value->sort()->values()->all())->toBe(['buyer', 'seller'])
        ->and($found->fresh()->owner_id)->toBe($this->ben->id)
        ->and($found->fresh()->phone)->toBe('555-111-2222');
});

test('reuse does not overwrite existing email or phone', function () {
    $c = Contact::factory()->for($this->ben, 'owner')->create(['email' => 'maria@x.com', 'phone' => '555-123-4567', 'roles' => ['buyer']]);

    $this->resolver->resolve('Maria', 'maria@x.com', '(555) 123-4567', ListingType::Sale, $this->anna);

    expect($c->fresh()->phone)->toBe('555-123-4567');
});
