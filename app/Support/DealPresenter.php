<?php

namespace App\Support;

use App\Models\Deal;
use Illuminate\Support\Str;

class DealPresenter
{
    /**
     * Row shape shared by the board, the list view and the client/property
     * tabs. Expects the relations in with() to be loaded.
     */
    public static function card(Deal $d): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title,
            'pipeline_id' => $d->pipeline_id,
            'stage_id' => $d->stage_id,
            'stage' => $d->stage ? ['id' => $d->stage->id, 'name' => $d->stage->name] : null,
            'position' => $d->position,
            'status' => $d->status->value,
            'value' => $d->value,
            'commission_percent' => (float) $d->commission_percent,
            'estimated_commission' => $d->estimated_commission,
            'expected_close_date' => $d->expected_close_date?->format('Y-m-d'),
            // The client may be soft-deleted (closed deals keep it): with() loads it withTrashed.
            'contact' => $d->contact ? ['id' => $d->contact->id, 'name' => $d->contact->name] : null,
            'property' => $d->property
                ? PropertyPresenter::ref($d->property) + ['cover_url' => $d->property->coverUrl()]
                : null,
            'owner' => [
                'id' => $d->owner->id,
                'name' => $d->owner->name,
                'initials' => self::initials($d->owner->name),
            ],
        ];
    }

    public static function initials(string $name): string
    {
        return Str::of($name)->explode(' ')->filter()->take(2)
            ->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))->implode('');
    }

    /** Relations to eager load before calling card(). */
    public static function with(): array
    {
        return ['stage:id,name', 'contact' => fn ($q) => $q->withTrashed()->select('id', 'first_name', 'last_name'), 'property:id,slug,title', 'property.cover', 'owner:id,name'];
    }
}
