<?php

namespace App\Models;

use App\Enums\ListingType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'listing_type'])]
class Pipeline extends Model
{
    protected function casts(): array
    {
        return ['listing_type' => ListingType::class];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('position');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public static function forListingType(ListingType $type): Pipeline
    {
        return static::query()->where('listing_type', $type->value)->firstOrFail();
    }

    /** The first stage by position. */
    public function inquiryStage(): Stage
    {
        return $this->stages()->firstOrFail();
    }
}
