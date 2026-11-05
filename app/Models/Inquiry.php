<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use App\Models\Concerns\HasOwner;
use Database\Factories\InquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** owner_id is the created deal's owner (the contact's agent). */
#[Fillable([
    'property_id', 'contact_id', 'deal_id', 'name', 'email', 'phone', 'message', 'wants_viewing',
    'preferred_contact', 'consent', 'source', 'ip', 'user_agent', 'status', 'owner_id',
])]
class Inquiry extends Model
{
    /** @use HasFactory<InquiryFactory> */
    use HasFactory, HasOwner, SoftDeletes;

    protected $attributes = [
        'status' => 'new',
        'source' => 'website',
        'preferred_contact' => 'email',
        'wants_viewing' => false,
        'consent' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => InquiryStatus::class,
            'wants_viewing' => 'boolean',
            'consent' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
