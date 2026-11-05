<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Models\Concerns\HasOwner;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** owner_id is the assignee. */
#[Fillable(['title', 'due_at', 'priority', 'completed_at', 'owner_id', 'contact_id', 'property_id', 'deal_id', 'inquiry_id'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasOwner, SoftDeletes;

    protected $attributes = ['priority' => 'normal'];

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('completed_at'));
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }
}
