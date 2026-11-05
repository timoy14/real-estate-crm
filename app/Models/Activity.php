<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Enums\ViewingStatus;
use App\Models\Concerns\HasOwner;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * owner_id is the author. Links are plain nullable FKs (contact, property,
 * deal); at least one is set, which the request layer enforces.
 */
#[Fillable([
    'type', 'subject', 'body', 'occurred_at', 'scheduled_at', 'duration_minutes', 'call_outcome',
    'viewing_status', 'feedback', 'contact_id', 'property_id', 'deal_id', 'owner_id',
])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, HasOwner, SoftDeletes;

    public const CALL_OUTCOMES = ['connected', 'no_answer', 'voicemail', 'busy'];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'viewing_status' => ViewingStatus::class,
            'occurred_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    /** Admins see everything. An agent sees what they wrote plus activity on contacts or deals they own. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where($q->qualifyColumn('owner_id'), $user->id)
                ->orWhereHas('contact', fn (Builder $c) => $c->where('owner_id', $user->id))
                ->orWhereHas('deal', fn (Builder $d) => $d->where('owner_id', $user->id));
        });
    }

    /** Newest first; a scheduled viewing sorts by its scheduled time. */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByRaw('coalesce(occurred_at, scheduled_at, created_at) desc')->orderByDesc('id');
    }

    public function author(): BelongsTo
    {
        return $this->owner();
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
}
