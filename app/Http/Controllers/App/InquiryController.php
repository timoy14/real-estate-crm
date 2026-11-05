<?php

namespace App\Http\Controllers\App;

use App\Enums\InquiryStatus;
use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InquiryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Inquiry::class);
        $user = $request->user();

        $status = InquiryStatus::tryFrom((string) $request->query('status')) ?? InquiryStatus::New;

        $inquiries = Inquiry::query()->visibleTo($user)
            ->where('status', $status->value)
            ->with(['property:id,title,slug', 'contact:id,first_name,last_name', 'deal:id,title'])
            ->latest('id')
            ->paginate(25)->withQueryString()
            ->through(fn (Inquiry $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'email' => $i->email,
                'phone' => $i->phone,
                'message' => $i->message,
                'wants_viewing' => $i->wants_viewing,
                'preferred_contact' => $i->preferred_contact,
                'status' => $i->status->value,
                'created_at' => $i->created_at?->toIso8601String(),
                'property' => $i->property ? ['id' => $i->property->id, 'title' => $i->property->title] : null,
                'contact' => $i->contact ? ['id' => $i->contact->id, 'name' => $i->contact->name] : null,
                'deal' => $i->deal ? ['id' => $i->deal->id, 'title' => $i->deal->title] : null,
            ]);

        $counts = Inquiry::query()->visibleTo($user)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return Inertia::render('app/Inquiries/Index', [
            'inquiries' => $inquiries,
            'status' => $status->value,
            'statusCounts' => collect(InquiryStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'options' => ['statuses' => EnumOptions::of(InquiryStatus::class)],
        ]);
    }

    public function update(Request $request, Inquiry $inquiry): RedirectResponse
    {
        Gate::authorize('update', $inquiry);

        $data = $request->validate([
            'status' => ['required', Rule::enum(InquiryStatus::class)],
        ]);

        $inquiry->update(['status' => $data['status']]);

        return back();
    }
}
