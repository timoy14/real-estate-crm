<?php

use App\Enums\ListingType;
use App\Enums\Role;
use App\Events\DealStageChanged;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use Database\Seeders\PipelineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PipelineSeeder::class);
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->anna = User::factory()->create(['name' => 'Anna Reyes']);
    $this->ben = User::factory()->create(['name' => 'Ben Carter']);
    $this->sales = Pipeline::forListingType(ListingType::Sale);
    $this->stage = fn (string $n) => $this->sales->stages()->where('name', $n)->firstOrFail();
});

function moveDeal($test, User $as, Deal $deal, string $stage, int $position = 0, array $extra = [])
{
    return $test->actingAs($as)->patch("/app/deals/{$deal->id}/move", [
        'stage_id' => ($test->stage)($stage)->id, 'position' => $position, ...$extra,
    ]);
}

test('board shows only own deals for agent, all for admin', function () {
    Deal::factory()->count(2)->create(['owner_id' => $this->anna->id]);
    Deal::factory()->count(3)->create(['owner_id' => $this->ben->id]);

    $this->actingAs($this->anna)->get('/app/deals')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('app/Deals/Board')
            ->where('pipeline.slug', 'sales')->has('pipelines', 2)->has('stages', 6)
            ->where('stages.0.count', 2));
    $this->actingAs($this->admin)->get('/app/deals')
        ->assertInertia(fn (Assert $p) => $p->where('stages.0.count', 5));
});

test('rentals pipeline and list view are selectable', function () {
    $this->actingAs($this->anna)->get('/app/deals?pipeline=rentals&view=list')
        ->assertInertia(fn (Assert $p) => $p->where('pipeline.slug', 'rentals')->has('stages', 5)->where('view', 'list'));
});

test('move to Closed Won sets status won and closed_at', function () {
    $deal = Deal::factory()->create(['owner_id' => $this->anna->id]);

    moveDeal($this, $this->anna, $deal, 'Closed Won')->assertRedirect();

    $deal->refresh();
    expect($deal->status->value)->toBe('won')->and($deal->closed_at)->not->toBeNull();
});

test('move to Lost stores lost_reason', function () {
    $deal = Deal::factory()->create(['owner_id' => $this->anna->id]);

    moveDeal($this, $this->anna, $deal, 'Lost', 0, ['lost_reason' => 'Went elsewhere'])->assertRedirect();

    $deal->refresh();
    expect($deal->status->value)->toBe('lost')
        ->and($deal->lost_reason)->toBe('Went elsewhere')
        ->and($deal->closed_at)->not->toBeNull();
});

test('move from Lost back to Offer resets status to open', function () {
    $deal = Deal::factory()->inStage('Lost')->create([
        'owner_id' => $this->anna->id, 'status' => 'lost', 'lost_reason' => 'x', 'closed_at' => now(),
    ]);

    moveDeal($this, $this->anna, $deal, 'Offer')->assertRedirect();

    $deal->refresh();
    expect($deal->status->value)->toBe('open')->and($deal->closed_at)->toBeNull()->and($deal->lost_reason)->toBeNull();
});

test('move reorders positions in both columns contiguously', function () {
    $mk = fn (string $stage, int $pos) => Deal::factory()->inStage($stage)->create([
        'owner_id' => $this->anna->id, 'position' => $pos,
    ]);
    [$a0, $a1, $a2] = [$mk('Inquiry', 0), $mk('Inquiry', 1), $mk('Inquiry', 2)];
    [$b0, $b1] = [$mk('Viewing', 0), $mk('Viewing', 1)];

    moveDeal($this, $this->anna, $a0, 'Viewing', 1)->assertRedirect();

    expect(Deal::where('stage_id', ($this->stage)('Inquiry')->id)->orderBy('position')->get()->map(fn ($d) => [$d->id, $d->position])->all())
        ->toBe([[$a1->id, 0], [$a2->id, 1]]);
    expect(Deal::where('stage_id', ($this->stage)('Viewing')->id)->orderBy('position')->get()->map(fn ($d) => [$d->id, $d->position])->all())
        ->toBe([[$b0->id, 0], [$a0->id, 1], [$b1->id, 2]]);

    // Within the same column.
    moveDeal($this, $this->anna, $b1, 'Viewing', 0)->assertRedirect();
    expect(Deal::where('stage_id', ($this->stage)('Viewing')->id)->orderBy('position')->pluck('id')->all())
        ->toBe([$b1->id, $b0->id, $a0->id]);
});

test('403 moving another agent\'s deal', function () {
    $deal = Deal::factory()->create(['owner_id' => $this->ben->id]);

    moveDeal($this, $this->anna, $deal, 'Offer')->assertForbidden();
    expect($deal->fresh()->stage_id)->toBe(($this->stage)('Inquiry')->id);
});

test('move rejects a stage from the other pipeline', function () {
    $deal = Deal::factory()->create(['owner_id' => $this->anna->id]);
    $rent = Pipeline::forListingType(ListingType::Rent)->inquiryStage();

    $this->actingAs($this->anna)->patch("/app/deals/{$deal->id}/move", ['stage_id' => $rent->id, 'position' => 0])
        ->assertSessionHasErrors('stage_id');
});

test('column total_value sums values', function () {
    Deal::factory()->create(['owner_id' => $this->anna->id, 'value' => 100000]);
    Deal::factory()->create(['owner_id' => $this->anna->id, 'value' => 250000]);
    Deal::factory()->create(['owner_id' => $this->anna->id, 'value' => null]);
    Deal::factory()->create(['owner_id' => $this->ben->id, 'value' => 999999]);

    $this->actingAs($this->anna)->get('/app/deals')
        ->assertInertia(fn (Assert $p) => $p->where('stages.0.total_value', 350000)->where('stages.0.count', 3));
});

test('move dispatches DealStageChanged', function () {
    Event::fake([DealStageChanged::class]);
    $deal = Deal::factory()->create(['owner_id' => $this->anna->id]);

    moveDeal($this, $this->anna, $deal, 'Offer')->assertRedirect();

    Event::assertDispatched(DealStageChanged::class, fn ($e) => $e->deal->is($deal)
        && $e->from->name === 'Inquiry' && $e->to->name === 'Offer');
});

test('reordering inside a column does not dispatch DealStageChanged', function () {
    Event::fake([DealStageChanged::class]);
    $deal = Deal::factory()->create(['owner_id' => $this->anna->id]);

    moveDeal($this, $this->anna, $deal, 'Inquiry', 0)->assertRedirect();

    Event::assertNotDispatched(DealStageChanged::class);
});

test('reordering inside Lost keeps lost_reason and closed_at', function () {
    $closed = now()->subDays(3)->startOfSecond();
    $deal = Deal::factory()->inStage('Lost')->create([
        'owner_id' => $this->anna->id, 'lost_reason' => 'Went elsewhere', 'closed_at' => $closed,
    ]);
    Deal::factory()->inStage('Lost')->create(['owner_id' => $this->anna->id]);

    moveDeal($this, $this->anna, $deal, 'Lost', 1)->assertRedirect();

    $deal->refresh();
    expect($deal->lost_reason)->toBe('Went elsewhere')->and($deal->closed_at->equalTo($closed))->toBeTrue();
});

test('reordering inside Closed Won keeps closed_at', function () {
    $closed = now()->subDays(3)->startOfSecond();
    $deal = Deal::factory()->inStage('Closed Won')->create(['owner_id' => $this->anna->id, 'closed_at' => $closed]);
    Deal::factory()->inStage('Closed Won')->create(['owner_id' => $this->anna->id]);

    moveDeal($this, $this->anna, $deal, 'Closed Won', 1)->assertRedirect();

    expect($deal->fresh()->closed_at->equalTo($closed))->toBeTrue();
});
