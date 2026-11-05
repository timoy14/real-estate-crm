<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreInquiryRequest;
use App\Models\Property;
use App\Services\Inquiries\ProcessInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class InquirySubmissionController extends Controller
{
    public function store(StoreInquiryRequest $request, string $slug, ProcessInquiry $process): RedirectResponse
    {
        $success = back()->with('success', 'Thanks! We will be in touch soon.');

        // Bots fill the hidden field: pretend it worked and store nothing.
        if ($request->filled('website')) {
            return $success;
        }

        // Slug only (no numeric-id fallback), as on the public detail page.
        // Missing, deleted, sold, unpublished: all get the same friendly error.
        $property = Property::query()->publiclyListed()->where('slug', $slug)->first();

        if (! $property) {
            throw ValidationException::withMessages(['property' => 'Sorry, this property is no longer available.']);
        }

        $data = $request->safe()->only(['name', 'email', 'phone', 'message', 'wants_viewing', 'preferred_contact']);
        $data['wants_viewing'] = $request->boolean('wants_viewing');

        $process->handle($property, $data, (string) $request->ip(), (string) $request->userAgent());

        return $success;
    }
}
