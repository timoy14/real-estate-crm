<?php

namespace App\Models;

use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable([
    'title', 'address', 'area', 'city', 'latitude', 'longitude', 'listing_type', 'property_type', 'price',
    'bedrooms', 'bathrooms', 'floor_area_sqft', 'lot_area_sqft', 'year_built', 'features', 'description',
    'status', 'is_published', 'hide_exact_address', 'owner_contact_id', 'listing_agent_id',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, SoftDeletes;

    public const FEATURES = [
        'parking', 'garage', 'pool', 'garden', 'balcony', 'furnished',
        'pets_allowed', 'air_conditioning', 'elevator', 'security', 'gym', 'sea_view',
    ];

    public const MAX_PHOTOS = 12;

    public const PLACEHOLDER = '/images/placeholder-property.svg';

    protected $attributes = [
        'status' => 'draft',
        'features' => '[]',
        'is_published' => false,
        'hide_exact_address' => false,
    ];

    protected function casts(): array
    {
        return [
            'listing_type' => ListingType::class,
            'property_type' => PropertyType::class,
            'status' => PropertyStatus::class,
            'features' => 'array',
            'price' => 'integer',
            'is_published' => 'boolean',
            'hide_exact_address' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function booted(): void
    {
        // The slug follows the title; the id suffix keeps it unique.
        static::saved(function (Property $p) {
            $slug = Str::slug($p->title).'-'.$p->id;
            if ($p->slug !== $slug) {
                $p->forceFill(['slug' => $slug])->saveQuietly();
            }
        });
    }

    /** @return list<array{value: string, label: string}> */
    public static function featureOptions(): array
    {
        return array_map(
            fn ($f) => ['value' => $f, 'label' => ucfirst(str_replace('_', ' ', $f))],
            self::FEATURES,
        );
    }

    /** Every property, for link pickers (properties are visible to everyone). */
    public static function pickerOptions(bool $withListingType = false): Collection
    {
        return static::query()->orderBy('title')
            ->get($withListingType ? ['id', 'title', 'listing_type'] : ['id', 'title'])
            ->map(fn (Property $p) => ['id' => $p->id, 'title' => $p->title]
                + ($withListingType ? ['listing_type' => $p->listing_type->value] : []))
            ->values();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Bind by slug; a purely numeric value is treated as an id. */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field === null && ctype_digit((string) $value)) {
            return $this->newQuery()->whereKey((int) $value)->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }

    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), PropertyStatus::Available->value)
            ->where($query->qualifyColumn('is_published'), true);
    }

    public function listingAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'listing_agent_id');
    }

    public function ownerContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'owner_contact_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PropertyPhoto::class)->orderBy('position')->orderBy('id');
    }

    /** The first photo in gallery order; eager load this instead of `photos` when only the cover is shown. */
    public function cover(): HasOne
    {
        return $this->hasOne(PropertyPhoto::class)->ofMany(['position' => 'min', 'id' => 'min']);
    }

    public function coverUrl(): string
    {
        $first = match (true) {
            $this->relationLoaded('cover') => $this->cover,
            $this->relationLoaded('photos') => $this->photos->first(),
            default => $this->photos()->first(),
        };

        return $first ? $first->url() : self::PLACEHOLDER;
    }
}
