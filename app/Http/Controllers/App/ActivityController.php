<?php

namespace App\Http\Controllers\App;

use App\Enums\ActivityType;
use App\Enums\ViewingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\ActivityRequest;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function store(ActivityRequest $request): RedirectResponse
    {
        $data = $request->activityData();
        $activity = new Activity($data);
        $activity->owner_id = $request->user()->id;

        // A deal implies its client.
        if ($activity->deal_id && ! $activity->contact_id) {
            $contactId = Deal::query()->whereKey($activity->deal_id)->value('contact_id');
            if ($contactId && Contact::query()->visibleTo($request->user())->whereKey($contactId)->exists()) {
                $activity->contact_id = $contactId;
            }
        }

        if ($activity->type === ActivityType::Viewing) {
            $activity->viewing_status = ViewingStatus::Scheduled;
            $activity->occurred_at = null;
        } else {
            $activity->occurred_at ??= now();
        }

        $activity->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => $activity->type === ActivityType::Viewing ? 'Viewing scheduled.' : 'Activity logged.']);

        return back();
    }

    public function update(ActivityRequest $request, Activity $activity): RedirectResponse
    {
        $activity->fill($request->activityData());

        if ($request->filled('viewing_status') && $activity->type === ActivityType::Viewing) {
            if ($activity->viewing_status === ViewingStatus::Completed && $activity->isDirty('viewing_status')) {
                $activity->occurred_at = now();
            } elseif ($activity->viewing_status !== ViewingStatus::Completed) {
                // A viewing that is no longer completed has not happened.
                $activity->occurred_at = null;
            }
        }
        $activity->save();

        return back();
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        Gate::authorize('delete', $activity);
        $activity->delete();

        return back();
    }
}
