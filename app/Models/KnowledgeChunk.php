<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

/** content_tsv is a generated column; never written. */
#[Fillable([
    'source_type', 'source_id', 'chunk_index', 'content', 'embedding',
    'owner_id', 'contact_id', 'property_id', 'deal_id', 'occurred_at',
])]
class KnowledgeChunk extends Model
{
    use HasNeighbors;

    protected function casts(): array
    {
        return [
            'embedding' => Vector::class,
            'occurred_at' => 'datetime',
            'source_id' => 'integer',
            'chunk_index' => 'integer',
            'owner_id' => 'integer',
            'contact_id' => 'integer',
            'property_id' => 'integer',
            'deal_id' => 'integer',
        ];
    }
}
