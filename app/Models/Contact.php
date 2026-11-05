<?php

namespace App\Models;

use App\Enums\ContactRole;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Models\Concerns\HasOwner;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable(['first_name', 'last_name', 'email', 'phone', 'roles', 'status', 'source', 'tags', 'notes', 'owner_id'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, HasOwner, SoftDeletes;

    protected function casts(): array
    {
        return [
            'roles' => AsEnumCollection::of(ContactRole::class),
            'status' => ContactStatus::class,
            'source' => ContactSource::class,
            'tags' => 'array',
        ];
    }

    protected $attributes = [
        'status' => 'new_lead',
        'roles' => '[]',
        'tags' => '[]',
    ];

    /** Digits only; an 11-digit number starting with 1 loses the leading 1. */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    protected function email(): Attribute
    {
        return Attribute::set(function (?string $value) {
            $value = $value === null ? null : mb_strtolower(trim($value));

            return $value === '' ? null : $value;
        });
    }

    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value) => [
            'phone' => $value === null || trim($value) === '' ? null : $value,
            'phone_normalized' => static::normalizePhone($value),
        ]);
    }

    /**
     * Contacts the user may link to, by name; $includeId (e.g. the current
     * property owner) is offered even when it belongs to someone else.
     */
    public static function pickerOptions(User $user, ?int $includeId = null): Collection
    {
        return static::query()
            ->where(fn ($q) => $q->visibleTo($user)->when($includeId, fn ($q) => $q->orWhere('id', $includeId)))
            ->orderBy('first_name')->orderBy('last_name')->get()
            ->map(fn (Contact $c) => ['id' => $c->id, 'name' => $c->name])->values();
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /** A client with open deals cannot be deleted: its deals would lose their client. */
    public function openDealsBlockingDelete(): ?string
    {
        $open = $this->deals()->where('status', 'open')->count();

        return $open > 0 ? "Close or reassign its {$open} open ".Str::plural('deal', $open).' first.' : null;
    }

    /** @throws ValidationException */
    public function ensureDeletable(string $key = 'client', bool $named = false): void
    {
        if ($message = $this->openDealsBlockingDelete()) {
            throw ValidationException::withMessages([$key => ($named ? $this->name.': ' : '').$message]);
        }
    }

    /**
     * Reassign the client and what hangs off it: open deals, open tasks and its
     * inquiries follow the new owner. Closed deals keep their history owner.
     * Saved one by one so the index observer sees each owner change.
     */
    public function reassignTo(int $ownerId): void
    {
        DB::transaction(function () use ($ownerId) {
            $this->update(['owner_id' => $ownerId]);
            $this->deals()->where('status', 'open')->where('owner_id', '!=', $ownerId)->get()
                ->each->update(['owner_id' => $ownerId]);
            Task::query()->where('contact_id', $this->id)->whereNull('completed_at')->where('owner_id', '!=', $ownerId)->get()
                ->each->update(['owner_id' => $ownerId]);
            Inquiry::query()->where('contact_id', $this->id)->where('owner_id', '!=', $ownerId)->get()
                ->each->update(['owner_id' => $ownerId]);
        });
    }
}
