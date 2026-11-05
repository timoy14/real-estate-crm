<?php

use App\Jobs\IndexRecordJob;
use App\Models\Contact;
use App\Models\KnowledgeChunk;
use App\Models\User;
use App\Services\Knowledge\Chunker;
use App\Services\Knowledge\Embedder;
use App\Services\Knowledge\FakeEmbedder;
use App\Services\Knowledge\KnowledgeIndexer;
use App\Services\Knowledge\RecordDocumenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('a queued index job removes chunks and does not embed when AI was turned off', function () {
    Http::preventStrayRequests();
    app()->bind(Embedder::class, FakeEmbedder::class);
    config(['crm.ai.enabled' => true]);
    $contact = Contact::factory()->create(['owner_id' => User::factory()->create()->id, 'notes' => 'likes lofts']);
    app(KnowledgeIndexer::class)->index($contact);
    expect(KnowledgeChunk::where('source_type', 'contact')->where('source_id', $contact->id)->exists())->toBeTrue();

    config(['crm.ai.enabled' => false]);
    $indexer = new KnowledgeIndexer(
        new class implements Embedder
        {
            public function embed(array $texts, string $inputType): array
            {
                throw new RuntimeException('embedder must not be used');
            }
        },
        app(RecordDocumenter::class),
        app(Chunker::class),
    );
    (new IndexRecordJob('contact', $contact->id))->handle($indexer);

    expect(KnowledgeChunk::where('source_type', 'contact')->where('source_id', $contact->id)->exists())->toBeFalse();
});
