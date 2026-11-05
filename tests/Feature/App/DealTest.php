<?php

use App\Enums\ListingType;
use App\Enums\Role;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\PipelineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PipelineSeeder::class);
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->anna = User::factory()->create(['name' => 'Anna Reyes']);
    $this->ben = User::factory()->create(['name' => 'Ben Carter']);
    $this->sales = Pipeline::forListingType(ListingType::Sale);
    $this->rentals = Pipeline::forListingType(ListingType::Rent);
});

function dealPayload($test, array $overrides = []): array
{
    $contact = Contact::factory()->for($test->anna, 'owner')->create();

    return array_merge([
        'title' => 'Brickell condo',
        'pipeline_id' => $test->sales->id,
        'stage_id' => $test->sales->stages()->where('name', 'Viewing')->first()->id,
        'contact_id' => $contact->id,
        'value' => 400000,
    ], $overrides);
}

test('pipeline seeder is idempotent and creates the exact stages', function () {
    $this->seed(PipelineSeeder::class);

    expect(Pipeline::count())->toBe(2);
    expect($this->sales->stages()->pluck('name')->all())
        ->toBe(['Inquiry', 'Viewing', 'Offer', 'Negotiation', 'Closed Won', 'Lost']);
    expect($this->rentals->stages()->pluck('probability')->all())->toBe([10, 30, 60, 100, 0]);
    expect($this->sales->inquiryStage()->name)->toBe('Inquiry');
});

test('store creates a deal in the chosen stage for own contact', function () {
    $payload = dealPayload($this);

    $this->actingAs($this->anna)->post('/app/deals', $payload)->assertRedirect();

    $deal = Deal::firstOrFail();
    expect($deal->stage_id)->toBe($payload['stage_id'])
        ->and($deal->owner_id)->toBe($this->anna->id)
        ->and($deal->position)->toBe(0)
        ->and($deal->status->value)->toBe('open')
        ->and((float) $deal->commission_percent)->toBe(3.0);
});

test('422 when contact_id belongs to another agent', function () {
    $others = Contact::factory()->for($this->ben, 'owner')->create();

    $this->actingAs($this->anna)->post('/app/deals', dealPayload($this, ['contact_id' => $others->id]))
        ->assertSessionHasErrors('contact_id');
    expect(Deal::count())->toBe(0);
});

test('422 when the stage belongs to the other pipeline', function () {
    $rentStage = $this->rentals->inquiryStage();

    $this->actingAs($this->anna)->post('/app/deals', dealPayload($this, ['stage_id' => $rentStage->id]))
        ->assertSessionHasErrors('stage_id');
    expect(Deal::count())->toBe(0);
});

test('403 viewing and updating another agent\'s deal', function () {
    $deal = Deal::factory()->create(['owner_id' => $this->ben->id]);

    $this->actingAs($this->anna)->get("/app/deals/{$deal->id}")->assertForbidden();
    $this->actingAs($this->anna)->patch("/app/deals/{$deal->id}", ['title' => 'Mine now'])->assertForbidden();
    $this->actingAs($this->anna)->delete("/app/deals/{$deal->id}")->assertForbidden();
    expect($deal->fresh()->title)->not->toBe('Mine now');
});

test('owner can update and delete a deal', function () {
    $deal = Deal::factory()->create(['owner_id' => $this->anna->id]);
    $contact = Contact::factory()->for($this->anna, 'owner')->create();

    $this->actingAs($this->anna)->patch("/app/deals/{$deal->id}", [
        'title' => 'Renamed', 'contact_id' => $contact->id, 'value' => 500000, 'commission_percent' => 2.5,
    ])->assertRedirect();
    expect($deal->fresh()->title)->toBe('Renamed')->and($deal->fresh()->value)->toBe(500000);

    $this->actingAs($this->anna)->delete("/app/deals/{$deal->id}")->assertRedirect();
    expect(Deal::find($deal->id))->toBeNull();
});

test('estimated_commission of 400000 at 3% is 12000', function () {
    $deal = Deal::factory()->make(['value' => 400000, 'commission_percent' => 3]);
    expect($deal->estimated_commission)->toBe(12000);

    $deal->value = null;
    expect($deal->estimated_commission)->toBeNull();
});

test('rent deal defaults commission_percent to 100', function () {
    $contact = Contact::factory()->for($this->anna, 'owner')->create();

    $this->actingAs($this->anna)->post('/app/deals', [
        'title' => 'Rental', 'pipeline_id' => $this->rentals->id,
        'stage_id' => $this->rentals->inquiryStage()->id, 'contact_id' => $contact->id, 'value' => 2500,
    ])->assertRedirect();

    expect((float) Deal::firstOrFail()->commission_percent)->toBe(100.0);
});

test('?new=1 board and show pages render', function () {
    $deal = Deal::factory()->create(['owner_id' => $this->anna->id]);

    $this->actingAs($this->anna)->get("/app/deals/{$deal->id}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('app/Deals/Show')->where('deal.id', $deal->id)->has('can'));
});

test('client and property show pages list deals', function () {
    $deal = Deal::factory()->create(['owner_id' => $this->anna->id]);
    $deal->contact->update(['owner_id' => $this->anna->id]);

    $this->actingAs($this->anna)->get("/app/clients/{$deal->contact_id}")
        ->assertInertia(fn ($p) => $p->has('deals', 1)->where('deals.0.id', $deal->id));
});

test('agent cannot see other agents\' deals on a property page', function () {
    $property = Property::factory()->create();
    Deal::factory()->create(['owner_id' => $this->ben->id, 'property_id' => $property->id]);
    Deal::factory()->create(['owner_id' => $this->anna->id, 'property_id' => $property->id]);

    $this->actingAs($this->anna)->get("/app/properties/{$property->id}")
        ->assertInertia(fn ($p) => $p->has('deals', 1));
});

// --- Final review fixes (F-I6) ---

test('deal show lists its pipeline stages with is_lost and the column size for a stage move', function () {
    $deal = Deal::factory()->create(['owner_id' => $this->anna->id]);
    Deal::factory()->inStage('Offer')->count(2)->create();

    $this->actingAs($this->anna)->get("/app/deals/{$deal->id}")
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p
            ->has('stages', 6)
            ->where('stages.2.name', 'Offer')
            ->where('stages.2.count', 2)
            ->where('stages.5.is_lost', true)
            ->where('stages.0.is_lost', false));
});
