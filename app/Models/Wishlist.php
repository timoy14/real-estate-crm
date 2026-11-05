<?php

namespace App\Models;

use App\Enums\ListingType;
use Database\Factories\WishlistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['listing_type', 'property_types', 'min_budget', 'max_budget', 'min_bedrooms', 'min_bathrooms', 'areas', 'must_have_features', 'move_in_date', 'notes'])]
class Wishlist extends Model
{
    /** @use HasFactory<WishlistFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'listing_type' => ListingType::class,
            'property_types' => 'array',
            'areas' => 'array',
            'must_have_features' => 'array',
            'min_budget' => 'integer',
            'max_budget' => 'integer',
            'move_in_date' => 'date:Y-m-d',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
