<?php

namespace App\Services\Matching;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PropertyMatcher
{
    /** @return Collection<int, Property> */
    public function forWishlist(Wishlist $w, int $limit = 20): Collection
    {
        $q = Property::query()
            ->where('status', PropertyStatus::Available->value)
            ->where('listing_type', $w->listing_type->value);

        $types = array_values($w->property_types ?? []);
        if ($types) {
            $q->whereIn('property_type', $types);
        }
        if ($w->min_budget !== null) {
            $q->where('price', '>=', $w->min_budget);
        }
        if ($w->max_budget !== null) {
            $q->where('price', '<=', $w->max_budget);
        }
        if ($w->min_bedrooms !== null) {
            $q->where('bedrooms', '>=', $w->min_bedrooms);
        }
        if ($w->min_bathrooms !== null) {
            $q->where('bathrooms', '>=', $w->min_bathrooms);
        }
        $areas = array_values(array_map(fn ($a) => mb_strtolower((string) $a), $w->areas ?? []));
        if ($areas) {
            $q->whereRaw('lower(area) in ('.implode(',', array_fill(0, count($areas), '?')).')', $areas);
        }

        $must = array_values($w->must_have_features ?? []);

        // Score and cut first; eager load only the survivors.
        return $q->get()
            ->each(fn (Property $p) => $p->setAttribute('match_score', $this->score($must, $p->features ?? [])))
            ->sortBy([['match_score', 'desc'], ['id', 'desc']])
            ->take($limit)
            ->values()
            ->load(['cover', 'listingAgent:id,name']);
    }

    /** @return Collection<int, Wishlist> */
    public function forProperty(Property $p, User $viewer, int $limit = 20): Collection
    {
        $q = Wishlist::query()
            ->where('listing_type', $p->listing_type->value)
            ->whereHas('contact', fn (Builder $c) => $c->visibleTo($viewer))
            ->whereRaw("(jsonb_array_length(coalesce(property_types, '[]'::jsonb)) = 0 or jsonb_exists(property_types, ?))", [$p->property_type->value])
            ->where(fn (Builder $b) => $b->whereNull('min_budget')->orWhere('min_budget', '<=', $p->price))
            ->where(fn (Builder $b) => $b->whereNull('max_budget')->orWhere('max_budget', '>=', $p->price))
            ->where(fn (Builder $b) => $b->whereNull('min_bedrooms')->orWhere('min_bedrooms', '<=', (int) $p->bedrooms))
            ->where(fn (Builder $b) => $b->whereNull('min_bathrooms')->orWhere('min_bathrooms', '<=', (int) $p->bathrooms))
            ->whereRaw("(jsonb_array_length(coalesce(areas, '[]'::jsonb)) = 0 or exists (select 1 from jsonb_array_elements_text(areas) a where lower(a) = lower(?)))", [(string) $p->area]);

        if ($p->status !== PropertyStatus::Available) {
            return new Collection;
        }

        return $q->get()
            ->each(fn (Wishlist $w) => $w->setAttribute('match_score', $this->score($w->must_have_features ?? [], $p->features ?? [])))
            ->sortBy([['match_score', 'desc'], ['id', 'desc']])
            ->take($limit)
            ->values()
            ->load('contact:id,first_name,last_name');
    }

    /** @param array<int,string> $must @param array<int,string> $has */
    private function score(array $must, array $has): int
    {
        if (! $must) {
            return 100;
        }

        return 60 + (int) floor(40 * count(array_intersect($must, $has)) / count($must));
    }
}
