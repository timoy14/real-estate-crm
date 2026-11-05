<?php

namespace App\Services\Knowledge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class VoyageEmbedder implements Embedder
{
    private const URL = 'https://api.voyageai.com/v1/embeddings';

    private const BATCH = 64;

    private const ATTEMPTS = 3;

    private const BACKOFF_MS = 500;

    public function embed(array $texts, string $inputType): array
    {
        $out = [];

        foreach (array_chunk(array_values($texts), self::BATCH) as $batch) {
            array_push($out, ...$this->embedBatch($batch, $inputType));
        }

        return $out;
    }

    /** @return list<list<float>> */
    private function embedBatch(array $batch, string $inputType): array
    {
        $response = null;

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            try {
                $response = Http::withToken((string) config('services.voyage.key'))
                    ->acceptJson()
                    ->asJson()
                    ->timeout(30)
                    ->post(self::URL, [
                        'input' => $batch,
                        'model' => config('services.voyage.model'),
                        'input_type' => $inputType,
                        'output_dimension' => (int) config('services.voyage.dimensions'),
                    ]);
            } catch (ConnectionException $e) {
                if ($attempt === self::ATTEMPTS) {
                    throw new EmbeddingFailed('Voyage connection failed: '.$e->getMessage(), 0, $e);
                }
                Sleep::usleep(self::BACKOFF_MS * 1000);

                continue;
            }

            if ($response->successful()) {
                break;
            }

            if (! $this->retryable($response) || $attempt === self::ATTEMPTS) {
                throw new EmbeddingFailed("Voyage request failed with status {$response->status()}.");
            }

            Sleep::usleep(self::BACKOFF_MS * 1000);
        }

        $data = collect($response->json('data', []))->sortBy('index')->values();

        if ($data->count() !== count($batch)) {
            throw new EmbeddingFailed('Voyage returned an unexpected number of embeddings.');
        }

        return $data->map(fn ($row) => array_map('floatval', $row['embedding']))->all();
    }

    private function retryable(Response $r): bool
    {
        return $r->status() === 429 || $r->status() >= 500;
    }
}
