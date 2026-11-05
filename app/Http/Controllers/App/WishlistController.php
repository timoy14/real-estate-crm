<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\WishlistRequest;
use App\Models\Contact;
use App\Models\Wishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class WishlistController extends Controller
{
    public function store(WishlistRequest $request, Contact $client): RedirectResponse
    {
        $client->wishlists()->create($request->wishlistData());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Wishlist saved.']);

        return back();
    }

    public function update(WishlistRequest $request, Wishlist $wishlist): RedirectResponse
    {
        $wishlist->update($request->wishlistData());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Wishlist saved.']);

        return back();
    }

    public function destroy(Wishlist $wishlist): RedirectResponse
    {
        abort_unless($wishlist->contact, 404);
        Gate::authorize('update', $wishlist->contact);
        $wishlist->delete();

        return back();
    }
}
