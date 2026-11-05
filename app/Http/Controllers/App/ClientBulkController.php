<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\BulkClientRequest;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ClientBulkController extends Controller
{
    public function __invoke(BulkClientRequest $request): RedirectResponse
    {
        $ids = array_values(array_unique($request->validated('ids')));
        $action = $request->validated('action');
        $ability = $action === 'delete' ? 'delete' : 'update';

        $contacts = Contact::query()->whereIn('id', $ids)->get();

        // All-or-nothing: one foreign id rejects the whole request.
        $contacts->each(fn (Contact $c) => Gate::authorize($ability, $c));

        // A client with open deals blocks the whole bulk delete.
        if ($action === 'delete') {
            $contacts->each(fn (Contact $c) => $c->ensureDeletable('ids', named: true));
        }

        DB::transaction(function () use ($contacts, $action, $request) {
            foreach ($contacts as $contact) {
                match ($action) {
                    'delete' => $contact->delete(),
                    'reassign' => $contact->reassignTo((int) $request->validated('owner_id')),
                    'set_status' => $contact->update(['status' => $request->validated('status')]),
                };
            }
        });

        $n = $contacts->count();
        Inertia::flash('toast', ['type' => 'success', 'message' => $n.' '.Str::plural('client', $n).' '.($action === 'delete' ? 'deleted.' : 'updated.')]);

        return back();
    }
}
