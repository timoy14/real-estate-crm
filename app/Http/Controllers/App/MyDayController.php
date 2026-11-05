<?php

namespace App\Http\Controllers\App;

use App\Enums\ActivityType;
use App\Enums\DealStatus;
use App\Enums\ViewingStatus;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\Task;
use App\Support\ActivityPresenter;
use App\Support\PropertyPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyDayController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        // The app timezone is America/New_York, so these are New York days.
        $now = now();

        $viewings = fn () => Activity::query()->visibleTo($user)
            ->where('type', ActivityType::Viewing->value)
            ->where('viewing_status', ViewingStatus::Scheduled->value);

        $tasks = fn () => Task::query()->where('owner_id', $user->id)->open()
            ->with(ActivityPresenter::taskWith())->orderBy('due_at')->orderBy('id');
        $taskRows = fn ($q) => $q->get()->map(fn (Task $t) => ActivityPresenter::task($t))->values();

        $viewingsToday = $viewings()
            ->whereBetween('scheduled_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->with(['property:id,title,slug', 'property.cover', 'contact:id,first_name,last_name'])
            ->orderBy('scheduled_at')->orderBy('id')->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'scheduled_at' => $a->scheduled_at->toIso8601String(),
                'subject' => $a->subject,
                'property' => $a->property
                    ? PropertyPresenter::ref($a->property) + ['cover' => $a->property->coverUrl()]
                    : null,
                'contact' => $a->contact ? ['id' => $a->contact->id, 'name' => $a->contact->name] : null,
            ])->values();

        $recent = Activity::query()->visibleTo($user)->where('owner_id', '!=', $user->id)
            ->with(ActivityPresenter::ACTIVITY_WITH)
            ->latestFirst()->limit(10)->get()
            ->map(fn (Activity $a) => ActivityPresenter::activity($a, $user))->values();

        return Inertia::render('app/MyDay', [
            'viewingsToday' => $viewingsToday,
            'overdueTasks' => $taskRows($tasks()->where('due_at', '<', $now)),
            'todayTasks' => $taskRows($tasks()->whereBetween('due_at', [$now, $now->copy()->endOfDay()])),
            'recentActivity' => $recent,
            'stats' => [
                'openDeals' => Deal::query()->visibleTo($user)->where('status', DealStatus::Open->value)->count(),
                'viewingsThisWeek' => $viewings()
                    ->whereBetween('scheduled_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->count(),
                'tasksDueToday' => Task::query()->where('owner_id', $user->id)->open()
                    ->whereBetween('due_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])->count(),
            ],
        ]);
    }
}
