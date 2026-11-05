<?php

namespace App\Http\Controllers\App;

use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\TaskRequest;
use App\Models\Task;
use App\Support\ActivityPresenter;
use App\Support\Agents;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Task::class);
        $user = $request->user();

        $now = now();
        $base = fn () => Task::query()->visibleTo($user)->with(ActivityPresenter::taskWith());
        $rows = fn ($tasks) => $tasks->map(fn (Task $t) => ActivityPresenter::task($t))->values();

        // One query for every open task, split here. Compared the way the database
        // compares the timestamp column: app-timezone wall clock, whole seconds.
        $at = fn ($d) => $d->format('Y-m-d H:i:s');
        [$nowAt, $endOfDayAt] = [$at($now), $at($now->endOfDay())];
        $open = $base()->open()->orderByRaw('due_at asc nulls last')->orderBy('id')->get()
            ->groupBy(fn (Task $t) => match (true) {
                $t->due_at === null => 'no_date',
                $at($t->due_at) < $nowAt => 'overdue',
                $at($t->due_at) <= $endOfDayAt => 'today',
                default => 'upcoming',
            });
        $group = fn (string $key) => $rows($open->get($key, collect()));

        return Inertia::render('app/Tasks/Index', [
            'groups' => [
                'overdue' => $group('overdue'),
                'today' => $group('today'),
                'upcoming' => $group('upcoming'),
                'no_date' => $group('no_date'),
                'completed' => $rows(
                    $base()->whereNotNull('completed_at')->where('completed_at', '>=', $now->subDays(7))
                        ->orderByDesc('completed_at')->orderBy('id')->get(),
                ),
            ],
            'options' => ['priorities' => EnumOptions::of(TaskPriority::class)],
            'link_options' => ActivityPresenter::linkOptions($user, deals: true),
            ...Agents::prop($user),
        ]);
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        $user = $request->user();
        $task = new Task($request->taskData());
        // Agents can only assign to themselves.
        $task->owner_id = $request->ownerIdFor() ?? $user->id;
        $task->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Task added.']);

        return back();
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $task->fill($request->taskData());
        if (($ownerId = $request->ownerIdFor()) !== null) {
            $task->owner_id = $ownerId;
        }
        if ($request->has('completed')) {
            $task->completed_at = $request->boolean('completed') ? now() : null;
        }
        $task->save();

        return back();
    }

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);
        $task->delete();

        return back();
    }
}
