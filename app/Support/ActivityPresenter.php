<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Property;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/** Row shapes and shared queries for the activity timeline and task lists. */
class ActivityPresenter
{
    /** Relations activity() reads (owner_id on contact and deal feeds the policy check). */
    public const ACTIVITY_WITH = ['author:id,name', 'contact:id,first_name,last_name,owner_id', 'property:id,title,slug', 'deal:id,title,owner_id'];

    /** Activities for a record's timeline, newest first, limited to what the user may see. */
    public static function timeline(User $user, string $column, int $id, int $limit = 100): Collection
    {
        return Activity::query()->visibleTo($user)->where($column, $id)
            ->with(self::ACTIVITY_WITH)
            ->latestFirst()->limit($limit)->get()
            ->map(fn (Activity $a) => self::activity($a, $user))->values();
    }

    /** Open tasks first, then the latest completed ones, for a record the user can see. */
    public static function tasksFor(User $user, string $column, int $id, int $limit = 50): Collection
    {
        return Task::query()->visibleTo($user)->where($column, $id)
            ->with(self::taskWith())
            ->orderByRaw('completed_at is not null')->orderByRaw('due_at asc nulls last')->orderBy('id')
            ->limit($limit)->get()
            ->map(fn (Task $t) => self::task($t))->values();
    }

    public static function taskWith(): array
    {
        return ['owner:id,name', 'contact:id,first_name,last_name', 'property:id,title,slug', 'deal:id,title'];
    }

    public static function activity(Activity $a, User $user): array
    {
        return [
            'id' => $a->id,
            'type' => $a->type->value,
            'subject' => $a->subject,
            'body' => $a->body,
            'occurred_at' => $a->occurred_at?->toIso8601String(),
            'scheduled_at' => $a->scheduled_at?->toIso8601String(),
            'duration_minutes' => $a->duration_minutes,
            'call_outcome' => $a->call_outcome,
            'viewing_status' => $a->viewing_status?->value,
            'feedback' => $a->feedback,
            'created_at' => $a->created_at?->toIso8601String(),
            'author' => ['id' => $a->author->id, 'name' => $a->author->name],
            'contact' => $a->contact ? ['id' => $a->contact->id, 'name' => $a->contact->name] : null,
            'property' => $a->property ? PropertyPresenter::ref($a->property) : null,
            'deal' => $a->deal ? ['id' => $a->deal->id, 'title' => $a->deal->title] : null,
            'can' => ['update' => $user->can('update', $a)],
        ];
    }

    public static function task(Task $t): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'due_at' => $t->due_at?->toIso8601String(),
            'priority' => $t->priority->value,
            'completed_at' => $t->completed_at?->toIso8601String(),
            'owner' => ['id' => $t->owner->id, 'name' => $t->owner->name],
            'contact' => $t->contact ? ['id' => $t->contact->id, 'name' => $t->contact->name] : null,
            'property' => $t->property ? PropertyPresenter::ref($t->property) : null,
            'deal' => $t->deal ? ['id' => $t->deal->id, 'title' => $t->deal->title] : null,
        ];
    }

    /** Pickers for the activity and task forms; every list is limited to what the user may link to. */
    public static function linkOptions(User $user, bool $deals = false): array
    {
        return [
            'contacts' => Contact::pickerOptions($user),
            'properties' => Property::pickerOptions(),
            'deals' => $deals
                ? Deal::query()->visibleTo($user)->orderBy('title')->get(['id', 'title'])
                    ->map(fn ($d) => ['id' => $d->id, 'title' => $d->title])->values()
                : [],
        ];
    }
}
