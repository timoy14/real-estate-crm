<?php

namespace App\Services\Knowledge;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Inquiry;
use App\Models\KnowledgeChunk;
use App\Models\Property;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KnowledgeIndexer
{
    /** @var array<string, class-string<Model>> */
    public const TYPES = [
        'contact' => Contact::class,
        'wishlist' => Wishlist::class,
        'property' => Property::class,
        'deal' => Deal::class,
        'activity' => Activity::class,
        'inquiry' => Inquiry::class,
    ];

    public function __construct(
        private Embedder $embedder,
        private RecordDocumenter $documenter,
        private Chunker $chunker,
    ) {}

    public static function typeOf(Model $m): ?string
    {
        $type = array_search($m::class, self::TYPES, true);

        return $type === false ? null : $type;
    }

    /** Replace all chunks for this record (removes them if it is not indexable). */
    public function index(Model $m): void
    {
        $this->indexMany([$m]);
    }

    /**
     * Index several records, embedding all their chunks together.
     *
     * @param  iterable<Model>  $models
     */
    public function indexMany(iterable $models): void
    {
        $plans = [];
        $texts = [];

        foreach ($models as $m) {
            $type = self::typeOf($m);
            if ($type === null) {
                continue;
            }
            $doc = $this->documenter->document($m);
            $parts = $doc ? $this->chunker->split($doc['content']) : [];
            $plans[] = [$type, $m->getKey(), $doc, $parts, count($texts)];
            array_push($texts, ...$parts);
        }

        $vectors = $texts === [] ? [] : $this->embedder->embed($texts, 'document');

        DB::transaction(function () use ($plans, $vectors) {
            foreach ($plans as [$type, $id, $doc, $parts, $offset]) {
                $this->remove($type, $id);
                foreach ($parts as $i => $content) {
                    KnowledgeChunk::create([
                        'source_type' => $type,
                        'source_id' => $id,
                        'chunk_index' => $i,
                        'content' => $content,
                        'embedding' => $vectors[$offset + $i],
                        'owner_id' => $doc['owner_id'],
                        'contact_id' => $doc['contact_id'],
                        'property_id' => $doc['property_id'],
                        'deal_id' => $doc['deal_id'],
                        'occurred_at' => $doc['occurred_at'],
                    ]);
                }
            }
        });
    }

    public function remove(string $type, int $id): void
    {
        KnowledgeChunk::query()->where('source_type', $type)->where('source_id', $id)->delete();
    }
}
