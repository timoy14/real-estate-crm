<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeChunk;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Permission-filtered hybrid search over knowledge_chunks.
 *
 * The visibility predicate is applied in SQL inside BOTH legs, before ranking
 * and limiting, so a hidden chunk can never occupy a top-N slot or be returned.
 * Nothing is filtered in PHP afterwards.
 */
class KnowledgeSearch
{
    public const LEG_LIMIT = 30;

    public const RRF_K = 60;

    public const MAX_LIMIT = 50;

    private const COLUMNS = 'id, source_type, source_id, chunk_index, content, owner_id, contact_id, property_id, deal_id, occurred_at, created_at';

    public function __construct(private Embedder $embedder) {}

    /**
     * @param  array{contact_id?: mixed, property_id?: mixed, deal_id?: mixed}  $scope
     * @return Collection<int, KnowledgeChunk> each with a `score` attribute
     */
    public function search(User $user, string $query, int $limit = 12, array $scope = []): Collection
    {
        $query = trim($query);
        $limit = max(1, min($limit, self::MAX_LIMIT));
        if ($query === '') {
            return new Collection;
        }

        [$where, $bindings] = $this->filters($user, $scope);

        $vectorIds = $this->vectorLeg($query, $where, $bindings);
        $keywordIds = $this->keywordLeg($query, $where, $bindings);

        $scores = [];
        foreach ([$vectorIds, $keywordIds] as $ranked) {
            foreach ($ranked as $i => $id) {
                $scores[$id] = ($scores[$id] ?? 0.0) + 1.0 / (self::RRF_K + $i + 1);
            }
        }
        if (! $scores) {
            return new Collection;
        }

        // Highest score first; ties broken by id for stable output.
        $ids = array_keys($scores);
        usort($ids, fn ($a, $b) => [$scores[$b], $b] <=> [$scores[$a], $a]);
        $ids = array_slice($ids, 0, $limit);

        // Re-fetch the winners (ids already passed the SQL filter; repeat it anyway).
        $rows = DB::select(
            'select '.self::COLUMNS.' from knowledge_chunks where id in ('.implode(',', array_fill(0, count($ids), '?')).') and '.$where,
            array_merge($ids, $bindings),
        );
        $byId = KnowledgeChunk::hydrate(array_map(fn ($r) => (array) $r, $rows))->keyBy('id');

        return collect($ids)
            ->filter(fn ($id) => $byId->has($id))
            ->map(function ($id) use ($byId, $scores) {
                $chunk = $byId[$id];
                $chunk->setAttribute('score', $scores[$id]);

                return $chunk;
            })
            ->values();
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function filters(User $user, array $scope): array
    {
        $parts = ['true'];
        $bindings = [];

        if (! $user->isAdmin()) {
            $parts[] = '(owner_id is null or owner_id = ?)';
            $bindings[] = $user->id;
        }

        // Fixed column names only; the scope can narrow results, never widen them.
        foreach (['contact_id', 'property_id', 'deal_id'] as $column) {
            $value = $scope[$column] ?? null;
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $parts[] = "{$column} = ?";
                $bindings[] = (int) $value;
            }
        }

        return [implode(' and ', $parts), $bindings];
    }

    /** @return list<int> */
    private function vectorLeg(string $query, string $where, array $bindings): array
    {
        // EmbeddingFailed propagates: the assistant must not answer over a half-working search.
        $embedding = $this->embedder->embed([$query], 'query')[0] ?? null;
        if (! $embedding) {
            return [];
        }
        $literal = '['.implode(',', array_map(fn ($x) => (float) $x, $embedding)).']';

        $rows = DB::select(
            "select id from knowledge_chunks where {$where} order by embedding <=> ?::vector, id limit ".self::LEG_LIMIT,
            array_merge($bindings, [$literal]),
        );

        return array_map(fn ($r) => (int) $r->id, $rows);
    }

    /** @return list<int> */
    private function keywordLeg(string $query, string $where, array $bindings): array
    {
        $rows = DB::select(
            "select id from knowledge_chunks where {$where} and content_tsv @@ websearch_to_tsquery('english', ?) ".
            "order by ts_rank(content_tsv, websearch_to_tsquery('english', ?)) desc, id limit ".self::LEG_LIMIT,
            array_merge($bindings, [$query, $query]),
        );

        return array_map(fn ($r) => (int) $r->id, $rows);
    }
}
