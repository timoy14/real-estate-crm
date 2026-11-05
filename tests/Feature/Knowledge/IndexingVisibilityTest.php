<?php

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\KnowledgeChunk;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\Knowledge\Embedder;
use App\Services\Knowledge\FakeEmbedder;
use App\Services\Knowledge\KnowledgeSearch;
use Database\Seeders\PipelineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PipelineSeeder::class);
    app()->bind(Embedder::class, FakeEmbedder::class);
    config(['crm.ai.enabled' => true]);
});

it('deleting a contact removes its wishlist chunks', function () {
    $c = Contact::factory()->create();
    $w = Wishlist::factory()->create(['contact_id' => $c->id]);
    expect(KnowledgeChunk::where('source_type', 'wishlist')->where('source_id', $w->id)->exists())->toBeTrue();

    $c->delete();

    expect(KnowledgeChunk::where('source_type', 'wishlist')->where('source_id', $w->id)->exists())->toBeFalse();
});

it('deleting a reassigned deal stops the old owner finding its activity chunks', function () {
    $ben = User::factory()->create();
    $anna = User::factory()->create();
    $deal = Deal::factory()->create(['owner_id' => $anna->id]);
    $act = Activity::factory()->create(['owner_id' => $anna->id, 'contact_id' => null, 'deal_id' => $deal->id,
        'subject' => 'Kitchen', 'body' => 'granite kitchen island']);
    $deal->update(['owner_id' => $ben->id]);
    expect(app(KnowledgeSearch::class)->search($ben, 'granite')->pluck('source_id')->all())->toContain($act->id);

    $deal->delete();

    expect(app(KnowledgeSearch::class)->search($ben, 'granite')->pluck('source_id')->all())->not->toContain($act->id);
});

it('restoring a deal re-owns its dependents again', function () {
    $anna = User::factory()->create();
    $deal = Deal::factory()->create(['owner_id' => $anna->id]);
    Activity::factory()->create(['owner_id' => $anna->id, 'contact_id' => null, 'deal_id' => $deal->id, 'body' => 'granite island']);
    $deal->delete();
    $deal->restore();

    expect(KnowledgeChunk::where('deal_id', $deal->id)->where('owner_id', $anna->id)->exists())->toBeTrue();
});

it('with AI off an owner change removes stale chunks of the record and dependents without http', function () {
    $ben = User::factory()->create();
    $anna = User::factory()->create();
    $contact = Contact::factory()->create(['owner_id' => $anna->id]);
    $w = Wishlist::factory()->create(['contact_id' => $contact->id]);
    expect(KnowledgeChunk::where('contact_id', $contact->id)->count())->toBeGreaterThan(0);

    config(['crm.ai.enabled' => false]);
    Http::preventStrayRequests();
    $contact->update(['owner_id' => $ben->id]);

    expect(KnowledgeChunk::where('source_type', 'contact')->where('source_id', $contact->id)->exists())->toBeFalse()
        ->and(KnowledgeChunk::where('source_type', 'wishlist')->where('source_id', $w->id)->exists())->toBeFalse();
});

it('with AI off deleting a record removes its chunks', function () {
    $contact = Contact::factory()->create();
    expect(KnowledgeChunk::where('source_type', 'contact')->where('source_id', $contact->id)->exists())->toBeTrue();

    config(['crm.ai.enabled' => false]);
    $contact->delete();

    expect(KnowledgeChunk::where('source_type', 'contact')->where('source_id', $contact->id)->exists())->toBeFalse();
});
