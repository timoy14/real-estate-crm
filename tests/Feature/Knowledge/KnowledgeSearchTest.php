<?php

use App\Enums\Role;
use App\Models\Contact;
use App\Models\KnowledgeChunk;
use App\Models\User;
use App\Services\Knowledge\Embedder;
use App\Services\Knowledge\FakeEmbedder;
use App\Services\Knowledge\KnowledgeSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Pgvector\Laravel\Distance;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->bind(Embedder::class, FakeEmbedder::class);
    $this->anna = User::factory()->create();
    $this->ben = User::factory()->create();
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

function chunk(string $content, ?int $ownerId, array $extra = []): KnowledgeChunk
{
    static $n = 1000;
    $n++;

    return KnowledgeChunk::create(array_merge([
        'source_type' => 'activity', 'source_id' => $n, 'chunk_index' => 0,
        'content' => $content,
        'embedding' => app(Embedder::class)->embed([$content], 'document')[0],
        'owner_id' => $ownerId, 'occurred_at' => now(),
    ], $extra));
}

it('agent never receives chunks owned by another agent', function () {
    $mine = chunk('Anna kitchen renovation note', $this->anna->id);
    $theirs = chunk('Ben kitchen secret offer', $this->ben->id);

    $ids = app(KnowledgeSearch::class)->search($this->anna, 'kitchen')->pluck('id')->all();

    expect($ids)->toContain($mine->id)->not->toContain($theirs->id);
});

it('does not leak other agents chunks through the vector leg either', function () {
    // Many Ben chunks identical to the query: vector top-30 would be all Ben's without in-SQL filtering.
    foreach (range(1, 35) as $i) {
        chunk('zebra', $this->ben->id);
    }
    $mine = chunk('zebra giraffe lion tiger', $this->anna->id);

    $ids = app(KnowledgeSearch::class)->search($this->anna, 'zebra', 12)->pluck('id')->all();

    expect($ids)->toBe([$mine->id]);
});

it('admin receives both', function () {
    $a = chunk('kitchen anna', $this->anna->id);
    $b = chunk('kitchen ben', $this->ben->id);

    $ids = app(KnowledgeSearch::class)->search($this->admin, 'kitchen')->pluck('id')->all();

    expect($ids)->toContain($a->id, $b->id);
});

it('property chunks are visible to all agents', function () {
    $p = chunk('Sunny loft with a big kitchen', null, ['source_type' => 'property']);

    expect(app(KnowledgeSearch::class)->search($this->anna, 'kitchen')->pluck('id')->all())->toContain($p->id)
        ->and(app(KnowledgeSearch::class)->search($this->ben, 'kitchen')->pluck('id')->all())->toContain($p->id);
});

it('a keyword-only match is returned even when its vector rank is low', function () {
    foreach (range(1, 40) as $i) {
        chunk('alpha alpha alpha alpha', $this->anna->id);
    }
    $kw = chunk('alpha plumbing issue discussed with seller', $this->anna->id);

    $results = app(KnowledgeSearch::class)->search($this->anna, 'alpha plumbing', 12);

    // Sanity: the 40 decoys outrank the keyword chunk on vector similarity alone.
    $q = app(Embedder::class)->embed(['alpha plumbing'], 'query')[0];
    $top = KnowledgeChunk::query()->nearestNeighbors('embedding', $q, Distance::Cosine)->limit(30)->pluck('id');
    expect($top)->not->toContain($kw->id);

    expect($results->pluck('id')->all())->toContain($kw->id)
        ->and($results->first()->id)->toBe($kw->id);
});

it('scope contact_id limits results', function () {
    $contact = Contact::factory()->create(['owner_id' => $this->anna->id]);
    $in = chunk('kitchen viewing', $this->anna->id, ['contact_id' => $contact->id]);
    $out = chunk('kitchen other', $this->anna->id, ['contact_id' => null]);

    $ids = app(KnowledgeSearch::class)->search($this->anna, 'kitchen', 12, ['contact_id' => $contact->id])->pluck('id')->all();

    expect($ids)->toBe([$in->id]);
});

it('scope cannot widen visibility', function () {
    $contact = Contact::factory()->create(['owner_id' => $this->ben->id]);
    chunk('kitchen ben', $this->ben->id, ['contact_id' => $contact->id]);

    expect(app(KnowledgeSearch::class)->search($this->anna, 'kitchen', 12, ['contact_id' => $contact->id]))->toBeEmpty();
});

it('limit is respected and results carry a score', function () {
    foreach (range(1, 20) as $i) {
        chunk("kitchen note {$i}", $this->anna->id);
    }

    $results = app(KnowledgeSearch::class)->search($this->anna, 'kitchen', 5);

    expect($results)->toHaveCount(5)
        ->and($results->first()->score)->toBeGreaterThan(0)
        ->and($results->first()->score)->toBeGreaterThanOrEqual($results->last()->score);
});

it('handles punctuation-only and empty queries without error', function () {
    chunk('kitchen', $this->anna->id);

    expect(app(KnowledgeSearch::class)->search($this->anna, '!!! ::: "', 5))->toBeInstanceOf(Collection::class)
        ->and(app(KnowledgeSearch::class)->search($this->anna, '   ', 5))->toBeEmpty();
});
