<?php

namespace App\Http\Controllers\App;

use App\Enums\ContactRole;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Enums\ListingType;
use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\StoreClientRequest;
use App\Http\Requests\App\UpdateClientRequest;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Property;
use App\Models\Wishlist;
use App\Services\Matching\PropertyMatcher;
use App\Support\ActivityPresenter;
use App\Support\Agents;
use App\Support\DealPresenter;
use App\Support\EnumOptions;
use App\Support\PropertyPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    private const SORTS = ['name', 'status', 'updated_at'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Contact::class);
        $user = $request->user();

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'role' => $request->query('role') ?: null,
            'status' => $request->query('status') ?: null,
            'tag' => $request->query('tag') ?: null,
            'owner_id' => $user->isAdmin() && $request->query('owner_id') ? (int) $request->query('owner_id') : null,
            'sort' => in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'updated_at',
            'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
        ];

        $query = Contact::query()->visibleTo($user)->with('owner:id,name');

        if ($filters['search'] !== '') {
            $this->applySearch($query, $filters['search']);
        }
        if (ContactRole::tryFrom((string) $filters['role'])) {
            $query->whereJsonContains('roles', $filters['role']);
        }
        if (ContactStatus::tryFrom((string) $filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if ($filters['tag']) {
            $query->whereJsonContains('tags', (string) $filters['tag']);
        }
        if ($filters['owner_id']) {
            $query->where('owner_id', $filters['owner_id']);
        }

        if ($filters['sort'] === 'name') {
            $query->orderBy('first_name', $filters['direction'])->orderBy('last_name', $filters['direction']);
        } else {
            $query->orderBy($filters['sort'], $filters['direction']);
        }
        $query->orderBy('id');

        $clients = $query->paginate(25)->withQueryString()
            ->through(fn (Contact $c) => $this->listRow($c));

        return Inertia::render('app/Clients/Index', [
            'clients' => $clients,
            'filters' => $filters,
            ...Agents::prop($user),
            'options' => $this->options(),
            'tags' => Contact::query()->visibleTo($user)
                ->selectRaw('distinct jsonb_array_elements_text(tags) as tag')
                ->orderBy('tag')->pluck('tag'),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $contact = new Contact($request->contactData());
        // Agents are always the owner; only admins may choose.
        $contact->owner_id = $request->ownerIdFor() ?? $request->user()->id;
        $contact->save();

        return to_route('app.clients.show', $contact);
    }

    public function show(Request $request, Contact $client, PropertyMatcher $matcher): Response
    {
        Gate::authorize('view', $client);
        $client->load('owner:id,name');
        $wishlists = $client->wishlists()->latest('id')->get();

        return Inertia::render('app/Clients/Show', [
            'client' => $this->detail($client),
            'wishlists' => $wishlists->map(fn (Wishlist $w) => $this->wishlistRow($w))->values(),
            'can' => [
                'update' => $request->user()->can('update', $client),
                'delete' => $request->user()->can('delete', $client),
            ],
            'options' => $this->options(),
            ...Agents::prop($request->user()),
            'matches' => $wishlists->map(fn (Wishlist $w) => [
                'wishlist_id' => $w->id,
                'properties' => $matcher->forWishlist($w)->map(fn (Property $p) => $this->matchRow($p))->values(),
            ])->values(),
            'deals' => Deal::query()->visibleTo($request->user())->where('contact_id', $client->id)
                ->with(DealPresenter::with())->latest('id')->get()
                ->map(fn ($d) => DealPresenter::card($d))->values(),
            'activities' => ActivityPresenter::timeline($request->user(), 'contact_id', $client->id),
            'tasks' => ActivityPresenter::tasksFor($request->user(), 'contact_id', $client->id),
            'link_options' => ActivityPresenter::linkOptions($request->user()),
        ]);
    }

    public function update(UpdateClientRequest $request, Contact $client): RedirectResponse
    {
        $client->fill($request->contactData());
        $newOwner = $request->ownerIdFor();
        DB::transaction(function () use ($client, $newOwner) {
            $client->save();
            if ($newOwner !== null && $newOwner !== $client->owner_id) {
                $client->reassignTo($newOwner);
            }
        });

        return back();
    }

    public function destroy(Contact $client): RedirectResponse
    {
        Gate::authorize('delete', $client);
        $client->ensureDeletable();
        $client->delete();

        return to_route('app.clients.index');
    }

    private function applySearch(Builder $query, string $term): void
    {
        $like = '%'.addcslashes($term, '\\%_').'%';
        $digits = Contact::normalizePhone($term);

        $query->where(function (Builder $q) use ($like, $digits) {
            $q->where('first_name', 'ilike', $like)
                ->orWhere('last_name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhereRaw("(first_name || ' ' || coalesce(last_name, '')) ilike ?", [$like]);
            if ($digits !== null && strlen($digits) >= 3) {
                $q->orWhere('phone_normalized', 'like', '%'.$digits.'%');
            }
        });
    }

    private function listRow(Contact $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => $c->phone,
            'roles' => $c->roles->map->value->values(),
            'status' => $c->status->value,
            'source' => $c->source?->value,
            'tags' => $c->tags ?? [],
            'owner' => ['id' => $c->owner->id, 'name' => $c->owner->name],
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }

    private function detail(Contact $c): array
    {
        return $this->listRow($c) + [
            'first_name' => $c->first_name,
            'last_name' => $c->last_name,
            'notes' => $c->notes,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }

    private function wishlistRow(Wishlist $w): array
    {
        return [
            'id' => $w->id,
            'listing_type' => $w->listing_type->value,
            'property_types' => $w->property_types ?? [],
            'min_budget' => $w->min_budget,
            'max_budget' => $w->max_budget,
            'min_bedrooms' => $w->min_bedrooms,
            'min_bathrooms' => $w->min_bathrooms,
            'areas' => $w->areas ?? [],
            'must_have_features' => $w->must_have_features ?? [],
            'move_in_date' => $w->move_in_date?->format('Y-m-d'),
            'notes' => $w->notes,
        ];
    }

    /** A matched property, as the MatchList shows it. */
    private function matchRow(Property $p): array
    {
        return PropertyPresenter::ref($p) + [
            'area' => $p->area,
            'price' => $p->price,
            'cover_url' => $p->coverUrl(),
            'match_score' => $p->match_score,
        ];
    }

    private function options(): array
    {
        return [
            'roles' => EnumOptions::of(ContactRole::class),
            'statuses' => EnumOptions::of(ContactStatus::class),
            'sources' => EnumOptions::of(ContactSource::class),
            'listing_types' => EnumOptions::of(ListingType::class),
            'property_types' => EnumOptions::of(PropertyType::class),
            'features' => Property::featureOptions(),
        ];
    }
}
