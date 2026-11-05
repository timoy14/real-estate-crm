<?php

use App\Services\Knowledge\EmbeddingFailed;
use App\Services\Knowledge\VoyageEmbedder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    config(['services.voyage.key' => 'test-key', 'services.voyage.model' => 'voyage-3.5', 'services.voyage.dimensions' => 1024]);
    Sleep::fake();
    Http::preventStrayRequests();
});

function voyageResponse(int $n, bool $reverse = false): array
{
    $data = array_map(fn ($i) => ['object' => 'embedding', 'index' => $i, 'embedding' => [(float) $i, 0.5]], range(0, $n - 1));

    return ['data' => $reverse ? array_reverse($data) : $data];
}

it('sends model, input_type, output_dimension and bearer token', function () {
    Http::fake(['api.voyageai.com/*' => Http::response(voyageResponse(1))]);

    (new VoyageEmbedder)->embed(['hello'], 'document');

    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.voyageai.com/v1/embeddings'
        && $r['model'] === 'voyage-3.5'
        && $r['input_type'] === 'document'
        && $r['output_dimension'] === 1024
        && $r['input'] === ['hello']
        && $r->hasHeader('Authorization', 'Bearer test-key'));
});

it('maps the response by index', function () {
    Http::fake(['*' => Http::response(voyageResponse(3, reverse: true))]);

    $out = (new VoyageEmbedder)->embed(['a', 'b', 'c'], 'query');

    expect($out)->toBe([[0.0, 0.5], [1.0, 0.5], [2.0, 0.5]]);
});

it('splits 70 texts into 2 requests', function () {
    Http::fake(fn (Request $r) => Http::response(voyageResponse(count($r['input']))));

    $out = (new VoyageEmbedder)->embed(array_fill(0, 70, 'x'), 'document');

    expect($out)->toHaveCount(70);
    Http::assertSentCount(2);
});

it('retries a 429 and succeeds', function () {
    Http::fakeSequence()->push(['error' => 'slow down'], 429)->push(voyageResponse(1));

    $out = (new VoyageEmbedder)->embed(['a'], 'document');

    expect($out)->toHaveCount(1);
    Http::assertSentCount(2);
});

it('throws EmbeddingFailed on a 400 without retrying', function () {
    Http::fake(['*' => Http::response(['detail' => 'bad'], 400)]);

    expect(fn () => (new VoyageEmbedder)->embed(['a'], 'document'))->toThrow(EmbeddingFailed::class);
    Http::assertSentCount(1);
});

it('throws EmbeddingFailed when retries are exhausted', function () {
    Http::fake(['*' => Http::response('boom', 503)]);

    expect(fn () => (new VoyageEmbedder)->embed(['a'], 'document'))->toThrow(EmbeddingFailed::class);
    Http::assertSentCount(3);
});
