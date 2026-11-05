<?php

use App\Enums\Role;
use App\Models\Inquiry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->anna = User::factory()->create();
    $this->ben = User::factory()->create();
    $this->property = Property::factory()->create(['listing_agent_id' => $this->anna->id]);
});

function inquiry($test, User $owner, array $o = []): Inquiry
{
    return Inquiry::factory()->create(array_merge(['owner_id' => $owner->id, 'property_id' => $test->property->id], $o));
}

test('agent sees only own inquiries', function () {
    inquiry($this, $this->anna, ['name' => 'Mine']);
    inquiry($this, $this->ben, ['name' => 'Theirs']);

    $this->actingAs($this->anna)->get('/app/inquiries')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('app/Inquiries/Index')
        ->has('inquiries.data', 1)
        ->where('inquiries.data.0.name', 'Mine'));
});

test('admin sees all inquiries and status filter works', function () {
    inquiry($this, $this->anna);
    inquiry($this, $this->ben, ['status' => 'contacted']);

    $this->actingAs($this->admin)->get('/app/inquiries?status=new')->assertInertia(fn (Assert $p) => $p->has('inquiries.data', 1));
    $this->actingAs($this->admin)->get('/app/inquiries?status=contacted')->assertInertia(fn (Assert $p) => $p->has('inquiries.data', 1));
    $this->actingAs($this->admin)->get('/app/inquiries?status=bogus')->assertOk();
});

test('status update to contacted', function () {
    $i = inquiry($this, $this->anna);

    $this->actingAs($this->anna)->patch("/app/inquiries/{$i->id}", ['status' => 'contacted'])->assertRedirect();

    expect($i->fresh()->status->value)->toBe('contacted');
});

test('invalid status is rejected', function () {
    $i = inquiry($this, $this->anna);

    $this->actingAs($this->anna)->patch("/app/inquiries/{$i->id}", ['status' => 'nope'])->assertSessionHasErrors('status');
});

test('403 updating another agents inquiry', function () {
    $i = inquiry($this, $this->ben);

    $this->actingAs($this->anna)->patch("/app/inquiries/{$i->id}", ['status' => 'spam'])->assertForbidden();
    expect($i->fresh()->status->value)->toBe('new');
});

test('counts.newInquiries counts only the users new inquiries', function () {
    inquiry($this, $this->anna);
    inquiry($this, $this->anna);
    inquiry($this, $this->anna, ['status' => 'contacted']);
    inquiry($this, $this->ben);

    $this->actingAs($this->anna)->get('/app/my-day')->assertInertia(fn (Assert $p) => $p->where('counts.newInquiries', 2));
});

test('property show lists only visible inquiries', function () {
    inquiry($this, $this->anna, ['name' => 'Mine']);
    inquiry($this, $this->ben, ['name' => 'Theirs']);

    $this->actingAs($this->anna)->get("/app/properties/{$this->property->id}")->assertInertia(fn (Assert $p) => $p
        ->has('inquiries', 1)
        ->where('inquiries.0.name', 'Mine'));
});
