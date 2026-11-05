<?php

namespace App\Jobs;

use App\Services\Knowledge\KnowledgeIndexer;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Unique while waiting: a burst of saves (e.g. a client reassignment touching its
 * deals, tasks and inquiries) queues one job per record. The lock is released when
 * the job starts, so a later change still queues a fresh one.
 */
class IndexRecordJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /** Safety net: a queued job that is never processed cannot hold its lock for longer. */
    public int $uniqueFor = 3600;

    public int $tries = 3;

    public function __construct(public string $type, public int $id) {}

    public function uniqueId(): string
    {
        return "{$this->type}:{$this->id}";
    }

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(KnowledgeIndexer $indexer): void
    {
        // Queued while AI was on, processed after the keys were removed: never call the embedder.
        if (! config('crm.ai.enabled')) {
            $indexer->remove($this->type, $this->id);

            return;
        }

        $class = KnowledgeIndexer::TYPES[$this->type] ?? null;
        $model = $class ? $class::query()->withTrashed()->find($this->id) : null;

        // Missing or soft-deleted records are not indexable; the documenter returns null for trashed ones.
        $model ? $indexer->index($model) : $indexer->remove($this->type, $this->id);
    }
}
