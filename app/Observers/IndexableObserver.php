<?php

namespace App\Observers;

use App\Jobs\IndexRecordJob;
use App\Jobs\RemoveRecordFromIndexJob;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Inquiry;
use App\Models\Wishlist;
use App\Services\Knowledge\KnowledgeIndexer;
use Illuminate\Database\Eloquent\Model;

class IndexableObserver
{
    public function saved(Model $model): void
    {
        if (! ($type = KnowledgeIndexer::typeOf($model))) {
            return;
        }
        $ownerChanged = $model->wasChanged('owner_id');

        if (! $this->enabled()) {
            // AI off: never embed, but never leave chunks owned by the previous owner.
            if ($ownerChanged) {
                RemoveRecordFromIndexJob::dispatch($type, $model->getKey())->afterCommit();
                $this->reindexDependents($model);
            }

            return;
        }

        IndexRecordJob::dispatch($type, $model->getKey())->afterCommit();

        if ($ownerChanged) {
            $this->reindexDependents($model);
        }
    }

    public function deleted(Model $model): void
    {
        if (! ($type = KnowledgeIndexer::typeOf($model))) {
            return;
        }

        RemoveRecordFromIndexJob::dispatch($type, $model->getKey())->afterCommit();

        // Dependents' chunk owners/visibility derive from this record.
        $this->reindexDependents($model);
    }

    public function restored(Model $model): void
    {
        if (KnowledgeIndexer::typeOf($model)) {
            $this->reindexDependents($model);
        }
    }

    /** Read at event time (not cached at boot) so seeders can switch indexing off. */
    private function enabled(): bool
    {
        return (bool) config('crm.ai.enabled');
    }

    /** Chunk owner_id of these records derives from the contact's or deal's owner. */
    private function reindexDependents(Model $model): void
    {
        // AI off: only remove (needs no embeddings); knowledge:rebuild repopulates later.
        $dispatch = fn (string $type, $query) => $query->pluck('id')
            ->each(fn ($id) => $this->enabled()
                ? IndexRecordJob::dispatch($type, $id)->afterCommit()
                : RemoveRecordFromIndexJob::dispatch($type, $id)->afterCommit());

        if ($model instanceof Contact) {
            $dispatch('wishlist', Wishlist::query()->where('contact_id', $model->id));
            $dispatch('deal', Deal::query()->where('contact_id', $model->id));
            $dispatch('activity', Activity::query()->where('contact_id', $model->id));
            $dispatch('inquiry', Inquiry::query()->where('contact_id', $model->id));
        } elseif ($model instanceof Deal) {
            $dispatch('activity', Activity::query()->where('deal_id', $model->id));
            $dispatch('inquiry', Inquiry::query()->where('deal_id', $model->id));
        }
    }
}
