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
class RemoveRecordFromIndexJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /** Safety net: a queued job that is never processed cannot hold its lock for longer. */
    public int $uniqueFor = 3600;

    public function __construct(public string $type, public int $id) {}

    public function uniqueId(): string
    {
        return "{$this->type}:{$this->id}";
    }

    public function handle(KnowledgeIndexer $indexer): void
    {
        $indexer->remove($this->type, $this->id);
    }
}
