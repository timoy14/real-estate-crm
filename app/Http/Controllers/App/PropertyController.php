<?php

namespace App\Http\Controllers\App;

use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\PropertyRequest;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Inquiry;
use App\Models\Property;
use App\Models\Wishlist;
use App\Services\Matching\PropertyMatcher;
use App\Support\ActivityPresenter;
use App\Support\Agents;
use App\Support\DealPresenter;
use App\Support\EnumOptions;
use App\Support\PropertyPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Property::class);
        $user = $request->user();

        $filters = [
            'listing_type' => $this->enumOrNull(ListingType::class, $request->query('listing_type')),
            'property_type' => $this->enumOrNull(PropertyType::class, $request->query('property_type')),
            'status' => $this->enumOrNull(PropertyStatus::class, $request->query('status')),
            'min_price' => $this->intOrNull($request->query('min_price')),
            'max_price' => $this->intOrNull($request->query('max_price')),
            'min_bedrooms' => $this->intOrNull($request->query('min_bedrooms')),
            'area' => trim((string) $request->query('area', '')) ?: null,
            'search' => trim((string) $request->query('search', '')),
            'view' => $request->query('view') === 'table' ? 'table' : 'grid',
        ];

        $query = Property::query()->with(['cover', 'listingAgent:id,name']);

        foreach (['listing_type', 'property_type', 'status', 'area'] as $col) {
            if ($filters[$col] !== null) {
                $query->where($col, $filters[$col]);
            }
        }
        if ($filters['min_price'] !== null) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if ($filters['max_price'] !== null) {
            $query->where('price', '<=', $filters['max_price']);
        }
        if ($filters['min_bedrooms'] !== null) {
            $query->where('bedrooms', '>=', $filters['min_bedrooms']);
        }
        if ($filters['search'] !== '') {
            $like = '%'.addcslashes($filters['search'], '\\%_').'%';
            $query->where(fn ($q) => $q->where('title', 'ilike', $like)->orWhere('address', 'ilike', $like));
        }

        $properties = $query->latest('id')->paginate(24)->withQueryString()
            ->through(fn (Property $p) => $this->listRow($p));

        return Inertia::render('app/Properties/Index', [
            'properties' => $properties,
            'filters' => $filters,
            'areas' => Property::query()->distinct()->orderBy('area')->pluck('area'),
            'options' => $this->options(),
            'contacts' => Contact::pickerOptions($user),
            ...Agents::prop($user),
        ]);
    }

    public function store(PropertyRequest $request): RedirectResponse
    {
        $property = new Property($request->propertyData());
        // Agents always list for themselves; only admins may choose.
        $property->listing_agent_id = $request->ownerIdFor('listing_agent_id') ?? $request->user()->id;
        $property->save();

        return to_route('app.properties.show', $property);
    }

    public function show(Request $request, Property $property, PropertyMatcher $matcher): Response
    {
        $user = $request->user();
        Gate::authorize('view', $property);
        $property->load(['photos', 'listingAgent:id,name']);

        $canOwner = $user->can('viewOwnerDetails', $property);
        $canUpdate = $user->can('update', $property);

        $c = $canOwner ? $property->ownerContact : null;
        $owner = $c ? ['id' => $c->id, 'name' => $c->name, 'email' => $c->email, 'phone' => $c->phone] : null;

        $detail = $this->listRow($property) + [
            'address' => $property->address,
            'latitude' => $property->latitude,
            'longitude' => $property->longitude,
            'lot_area_sqft' => $property->lot_area_sqft,
            'year_built' => $property->year_built,
            'features' => $property->features ?? [],
            'description' => $property->description,
            'is_published' => $property->is_published,
            'hide_exact_address' => $property->hide_exact_address,
            'created_at' => $property->created_at?->toIso8601String(),
        ];
        if ($canOwner) {
            $detail['owner_contact_id'] = $property->owner_contact_id;
        }

        return Inertia::render('app/Properties/Show', [
            'property' => $detail,
            'photos' => $property->photos->map(fn ($ph) => [
                'id' => $ph->id,
                'url' => $ph->url(),
                'position' => $ph->position,
            ])->values(),
            'owner' => $owner,
            'can' => ['update' => $canUpdate, 'viewOwnerDetails' => $canOwner],
            'options' => $this->options(),
            'feature_options' => Property::FEATURES,
            ...($canUpdate ? ['contacts' => Contact::pickerOptions($user, $property->owner_contact_id)] : []),
            ...Agents::prop($user),
            'interestedClients' => $matcher->forProperty($property, $user)->map(fn (Wishlist $w) => [
                'wishlist_id' => $w->id,
                'contact' => ['id' => $w->contact->id, 'name' => $w->contact->name],
                'match_score' => $w->match_score,
            ])->values(),
            'deals' => Deal::query()->visibleTo($user)->where('property_id', $property->id)
                ->with(DealPresenter::with())->latest('id')->get()
                ->map(fn ($d) => DealPresenter::card($d))->values(),
            'inquiries' => Inquiry::query()->visibleTo($user)->where('property_id', $property->id)
                ->latest('id')->limit(50)->get()
                ->map(fn (Inquiry $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'status' => $i->status->value,
                    'status_label' => $i->status->label(),
                    'wants_viewing' => $i->wants_viewing,
                    'contact_id' => $i->contact_id,
                    'created_at' => $i->created_at?->toIso8601String(),
                ])->values(),
            'activities' => ActivityPresenter::timeline($user, 'property_id', $property->id),
            'tasks' => ActivityPresenter::tasksFor($user, 'property_id', $property->id),
            'link_options' => ActivityPresenter::linkOptions($user),
        ]);
    }

    public function update(PropertyRequest $request, Property $property): RedirectResponse
    {
        $property->fill($request->propertyData());
        if (($agentId = $request->ownerIdFor('listing_agent_id')) !== null) {
            $property->listing_agent_id = $agentId;
        }
        $property->save(); // The model's saved hook keeps the slug in step with the title.

        return to_route('app.properties.show', $property);
    }

    public function destroy(Property $property): RedirectResponse
    {
        Gate::authorize('delete', $property);
        $property->delete();

        return to_route('app.properties.index');
    }

    private function listRow(Property $p): array
    {
        return PropertyPresenter::ref($p) + [
            'area' => $p->area,
            'city' => $p->city,
            'listing_type' => $p->listing_type->value,
            'property_type' => $p->property_type->value,
            'price' => $p->price,
            'bedrooms' => $p->bedrooms,
            'bathrooms' => $p->bathrooms,
            'floor_area_sqft' => $p->floor_area_sqft,
            'status' => $p->status->value,
            'cover_url' => $p->coverUrl(),
            'listing_agent' => $p->listingAgent ? ['id' => $p->listingAgent->id, 'name' => $p->listingAgent->name] : null,
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    private function options(): array
    {
        return [
            'listing_types' => EnumOptions::of(ListingType::class),
            'property_types' => EnumOptions::of(PropertyType::class),
            'statuses' => EnumOptions::of(PropertyStatus::class),
            'features' => Property::featureOptions(),
        ];
    }

    private function enumOrNull(string $enum, mixed $value): ?string
    {
        return is_string($value) && $enum::tryFrom($value) ? $value : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 0 ? (int) $value : null;
    }
}
