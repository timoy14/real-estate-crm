<?php

namespace App\Services\Assistant;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Inquiry;
use App\Models\Property;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Gate;

/**
 * Turns "type:id" refs into {ref, label, url}, re-checking visibility for the asking user.
 * Refs the user may not view (or that no longer exist) are dropped.
 */
class RefResolver
{
    /** Record types a ref may name, as a regex alternation. */
    private const TYPES = 'contact|property|deal|activity|wishlist|inquiry';

    /** A ref cited in assistant text: "[type:id]". */
    public const PATTERN = '/\[('.self::TYPES.'):(\d+)\]/';

    /** Whether $value is a bare "type:id" ref. */
    public static function isRef(mixed $value): bool
    {
        return self::parse($value) !== null;
    }

    /** @return array{0: string, 1: int}|null [type, id] of a bare "type:id" ref */
    public static function parse(mixed $value): ?array
    {
        return is_string($value) && preg_match('/^('.self::TYPES.'):(\d+)$/', $value, $m) ? [$m[1], (int) $m[2]] : null;
    }

    /**
     * @param  iterable<string>  $refs  e.g. ["contact:12"]
     * @return list<array{ref: string, label: string, url: string}>
     */
    public function resolve(User $user, iterable $refs): array
    {
        $out = [];
        $seen = [];
        foreach ($refs as $ref) {
            if (! is_string($ref) || isset($seen[$ref]) || ! ($parsed = self::parse($ref))) {
                continue;
            }
            $seen[$ref] = true;
            $found = $this->one($user, ...$parsed);
            if ($found) {
                $out[] = ['ref' => $ref, 'label' => $found[0], 'url' => $found[1]];
            }
        }

        return $out;
    }

    /** Label of a record the user can see, or null. */
    public function label(User $user, string $type, int $id): ?string
    {
        return $this->one($user, $type, $id)[0] ?? null;
    }

    /** @return array{0: string, 1: string}|null */
    private function one(User $user, string $type, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        return match ($type) {
            'contact' => $this->contact($user, $id),
            'property' => $this->property($user, $id),
            'deal' => $this->deal($user, $id),
            'wishlist' => $this->wishlist($user, $id),
            'activity' => $this->activity($user, $id),
            'inquiry' => $this->inquiry($user, $id),
            default => null,
        };
    }

    private function contact(User $user, int $id): ?array
    {
        $c = Contact::query()->visibleTo($user)->find($id);

        return $c ? [$this->clean($c->name), route('app.clients.show', $c->id, false)] : null;
    }

    private function property(User $user, int $id): ?array
    {
        $p = Property::query()->find($id);

        return $p && Gate::forUser($user)->allows('view', $p)
            ? [$this->clean($p->title), route('app.properties.show', $p->id, false)] : null;
    }

    private function deal(User $user, int $id): ?array
    {
        $d = Deal::query()->visibleTo($user)->find($id);

        return $d ? [$this->clean($d->title), route('app.deals.show', $d->id, false)] : null;
    }

    private function wishlist(User $user, int $id): ?array
    {
        $w = Wishlist::query()->whereHas('contact', fn ($q) => $q->visibleTo($user))->with('contact')->find($id);

        return $w ? [$this->clean('Wishlist: '.$w->contact->name), route('app.clients.show', $w->contact_id, false)] : null;
    }

    private function activity(User $user, int $id): ?array
    {
        $a = Activity::query()->find($id);
        if (! $a || ! Gate::forUser($user)->allows('view', $a)) {
            return null;
        }
        // Link to the parent page the user can open.
        $url = null;
        if ($a->deal_id && Deal::query()->visibleTo($user)->whereKey($a->deal_id)->exists()) {
            $url = route('app.deals.show', $a->deal_id, false);
        } elseif ($a->contact_id && Contact::query()->visibleTo($user)->whereKey($a->contact_id)->exists()) {
            $url = route('app.clients.show', $a->contact_id, false);
        } elseif ($a->property_id) {
            $url = route('app.properties.show', $a->property_id, false);
        }

        return $url ? [$this->clean('Activity: '.($a->subject ?: $a->type->value)), $url] : null;
    }

    private function inquiry(User $user, int $id): ?array
    {
        $i = Inquiry::query()->find($id);

        return $i && Gate::forUser($user)->allows('view', $i)
            ? [$this->clean('Inquiry from '.$i->name), route('app.inquiries.index', [], false)] : null;
    }

    /** Labels are user-entered text: one line, bounded. */
    private function clean(?string $s): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', (string) $s) ?? ''), 0, 100);
    }
}
