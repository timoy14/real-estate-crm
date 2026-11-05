<?php

namespace App\Console\Commands;

use App\Models\KnowledgeChunk;
use App\Services\Knowledge\EmbeddingFailed;
use App\Services\Knowledge\KnowledgeIndexer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class KnowledgeRebuild extends Command
{
    protected $signature = 'knowledge:rebuild {--type= : Only rebuild one source type}';

    protected $description = 'Delete and rebuild the AI knowledge index';

    public function handle(KnowledgeIndexer $indexer): int
    {
        if (! config('crm.ai.enabled')) {
            $this->components->warn('AI is not configured');

            return self::SUCCESS;
        }

        $only = $this->option('type');
        if ($only && ! isset(KnowledgeIndexer::TYPES[$only])) {
            $this->components->error('Unknown type. Use one of: '.implode(', ', array_keys(KnowledgeIndexer::TYPES)));

            return self::FAILURE;
        }

        $types = $only ? [$only => KnowledgeIndexer::TYPES[$only]] : KnowledgeIndexer::TYPES;

        KnowledgeChunk::query()->when($only, fn ($q) => $q->where('source_type', $only))->delete();

        $total = 0;
        foreach ($types as $class) {
            $total += $class::query()->count();
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        try {
            foreach ($types as $class) {
                /** @var class-string<Model> $class */
                $class::query()->chunkById(100, function (Collection $batch) use ($indexer, $bar) {
                    $indexer->indexMany($batch);
                    $bar->advance($batch->count());
                });
            }
        } catch (EmbeddingFailed $e) {
            $bar->finish();
            $this->newLine();
            $this->components->error('Embedding failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine();
        $this->components->info('Knowledge index rebuilt.');

        return self::SUCCESS;
    }
}
