<?php

namespace App\Http\Resources;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The ONLY shape in which a property leaves the server for the public site.
 * Allow-list: every field is named explicitly. Never add owner, notes, status,
 * internal ids or the listing agent's id/email here.
 *
 * @mixin Property
 */
class PublicPropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Property $p */
        $p = $this->resource;
        $hide = $p->hide_exact_address;

        return [
            'slug' => $p->slug,
            'title' => $p->title,
            'listing_type' => $p->listing_type->value,
            'property_type' => $p->property_type->value,
            'price' => $p->price,
            'bedrooms' => $p->bedrooms,
            'bathrooms' => $p->bathrooms,
            'floor_area_sqft' => $p->floor_area_sqft,
            'lot_area_sqft' => $p->lot_area_sqft,
            'year_built' => $p->year_built,
            'features' => $p->features ?? [],
            'description' => $p->description,
            'area' => $p->area,
            'city' => $p->city,
            'address' => $hide ? null : $p->address,
            'latitude' => $hide ? null : $p->latitude,
            'longitude' => $hide ? null : $p->longitude,
            'photos' => $p->photos->map(fn ($photo) => $photo->url())->values()->all(),
            'cover_url' => $p->coverUrl(),
            'agent' => $p->listingAgent ? [
                'name' => $p->listingAgent->name,
                'avatar_url' => null,
            ] : null,
        ];
    }
}
