<?php

namespace App\Services\Knowledge;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Inquiry;
use App\Models\Property;
use App\Models\Wishlist;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Turns a CRM record into plain-English text for embedding. Free text written by customers
 * (inquiry messages, notes) is included verbatim as content; no instructions are added here.
 */
class RecordDocumenter
{
    /**
     * @return array{content: string, owner_id: ?int, contact_id: ?int, property_id: ?int, deal_id: ?int, occurred_at: ?CarbonInterface}|null
     */
    public function document(Model $m): ?array
    {
        if (method_exists($m, 'trashed') && $m->trashed()) {
            return null;
        }

        return match (true) {
            $m instanceof Contact => $this->contact($m),
            $m instanceof Wishlist => $this->wishlist($m),
            $m instanceof Property => $this->property($m),
            $m instanceof Deal => $this->deal($m),
            $m instanceof Activity => $this->activity($m),
            $m instanceof Inquiry => $this->inquiry($m),
            default => null,
        };
    }

    private function contact(Contact $c): array
    {
        $lines = [
            "[contact:{$c->id}] {$c->name}",
            $this->line('Roles', $this->labels($c->roles)),
            $this->line('Status', $this->label($c->status)),
            $this->line('Source', $this->label($c->source)),
            $this->line('Email', $c->email),
            $this->line('Phone', $c->phone),
            $this->line('Tags', implode(', ', $c->tags ?? [])),
            $this->line('Notes', $c->notes ?? null),
        ];

        return $this->result($lines, owner: $c->owner_id, contact: $c->id);
    }

    private function wishlist(Wishlist $w): ?array
    {
        $contact = $w->contact()->first();
        if (! $contact) {
            return null;
        }

        $budget = match (true) {
            $w->min_budget && $w->max_budget => '₱'.number_format($w->min_budget).' to ₱'.number_format($w->max_budget),
            (bool) $w->max_budget => 'up to ₱'.number_format($w->max_budget),
            (bool) $w->min_budget => 'from ₱'.number_format($w->min_budget),
            default => null,
        };

        $lines = [
            "[wishlist:{$w->id}] {$contact->name} wants: ".($this->label($w->listing_type) ?? 'a property'),
            $this->line('Property types', $this->humanList($w->property_types ?? [])),
            $this->line('Budget', $budget),
            $this->line('Minimum bedrooms', $w->min_bedrooms),
            $this->line('Minimum bathrooms', $w->min_bathrooms),
            $this->line('Areas', implode(', ', $w->areas ?? [])),
            $this->line('Must-have features', $this->humanList($w->must_have_features ?? [])),
            $this->line('Move-in date', $w->move_in_date?->format('Y-m-d')),
            $this->line('Notes', $w->notes),
        ];

        return $this->result($lines, owner: $contact->owner_id, contact: $contact->id);
    }

    /** Seller/landlord (owner contact) details are deliberately never read here. */
    private function property(Property $p): array
    {
        $lines = [
            "[property:{$p->id}] {$p->title}",
            $this->line('Listing', $this->label($p->listing_type)),
            $this->line('Type', $this->label($p->property_type)),
            $this->line('Price', $p->price !== null ? '₱'.number_format($p->price) : null),
            $this->line('Bedrooms', $p->bedrooms),
            $this->line('Bathrooms', $p->bathrooms),
            $this->line('Floor area', $p->floor_area_sqft ? number_format($p->floor_area_sqft).' sqft' : null),
            $this->line('Lot area', $p->lot_area_sqft ? number_format($p->lot_area_sqft).' sqft' : null),
            $this->line('Year built', $p->year_built),
            $this->line('Location', implode(', ', array_filter([$p->area, $p->city]))),
            $this->line('Features', $this->humanList($p->features ?? [])),
            $this->line('Status', $this->label($p->status)),
            $this->line('Description', $p->description),
        ];

        return $this->result($lines, owner: null, property: $p->id);
    }

    private function deal(Deal $d): array
    {
        $d->loadMissing(['pipeline:id,name', 'stage:id,name', 'contact:id,first_name,last_name', 'property:id,title']);

        $lines = [
            "[deal:{$d->id}] {$d->title}",
            $this->line('Pipeline', $d->pipeline?->name),
            $this->line('Stage', $d->stage?->name),
            $this->line('Status', $this->label($d->status)),
            $this->line('Value', $d->value !== null ? '₱'.number_format($d->value) : null),
            $this->line('Client', $d->contact?->name),
            $this->line('Property', $d->property?->title),
            $this->line('Expected close date', $d->expected_close_date?->format('Y-m-d')),
            $this->line('Lost reason', $d->lost_reason),
        ];

        return $this->result($lines, owner: $d->owner_id, contact: $d->contact_id, property: $d->property_id, deal: $d->id);
    }

    private function activity(Activity $a): array
    {
        $a->loadMissing(['contact:id,first_name,last_name,owner_id', 'property:id,title', 'deal:id,title,owner_id']);

        $when = $a->occurred_at ?? $a->scheduled_at;
        $label = $a->subject ?: ($this->label($a->type) ?? 'Activity');

        $lines = [
            "[activity:{$a->id}] {$label}",
            $this->line('Type', $this->label($a->type)),
            $this->line($a->occurred_at ? 'Date' : 'Scheduled for', $when?->format('Y-m-d H:i')),
            $this->line('Subject', $a->subject),
            $this->line('Notes', $a->body),
            $this->line('Call outcome', $a->call_outcome ? $this->humanize($a->call_outcome) : null),
            $this->line('Viewing status', $this->label($a->viewing_status)),
            $this->line('Viewing feedback', $a->feedback),
            $this->line('Client', $a->contact?->name),
            $this->line('Property', $a->property?->title),
            $this->line('Deal', $a->deal?->title),
        ];

        // Chunk owner: linked deal's owner, else linked contact's owner, else the author.
        $owner = $a->deal?->owner_id ?? $a->contact?->owner_id ?? $a->owner_id;

        return $this->result($lines, $owner, $a->contact_id, $a->property_id, $a->deal_id, $when ?? $a->created_at);
    }

    private function inquiry(Inquiry $i): array
    {
        $i->loadMissing('property:id,title');

        $lines = [
            "[inquiry:{$i->id}] Website inquiry from {$i->name}",
            $this->line('Property', $i->property?->title),
            $this->line('Message', $i->message),
            $this->line('Wants a viewing', $i->wants_viewing ? 'yes' : 'no'),
            $this->line('Status', $this->label($i->status)),
        ];

        return $this->result($lines, $i->owner_id, $i->contact_id, $i->property_id, $i->deal_id, $i->created_at);
    }

    /** @param list<?string> $lines */
    private function result(array $lines, ?int $owner, ?int $contact = null, ?int $property = null, ?int $deal = null, ?CarbonInterface $at = null): array
    {
        return [
            'content' => implode("\n", array_filter($lines, fn ($l) => $l !== null)),
            'owner_id' => $owner,
            'contact_id' => $contact,
            'property_id' => $property,
            'deal_id' => $deal,
            'occurred_at' => $at,
        ];
    }

    /** "pets_allowed" → "pets allowed". */
    private function humanize(string $value): string
    {
        return str_replace('_', ' ', $value);
    }

    /** @param  array<int, string>  $values */
    private function humanList(array $values): string
    {
        return implode(', ', array_map(fn ($v) => $this->humanize($v), $values));
    }

    private function line(string $label, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return "{$label}: {$value}";
    }

    private function label(mixed $enum): ?string
    {
        if (! $enum instanceof UnitEnum) {
            return $enum === null ? null : (string) $enum;
        }

        return method_exists($enum, 'label') ? $enum->label() : ($enum->value ?? $enum->name);
    }

    private function labels(iterable $enums): string
    {
        $out = [];
        foreach ($enums as $e) {
            $out[] = $this->label($e);
        }

        return implode(', ', $out);
    }
}
