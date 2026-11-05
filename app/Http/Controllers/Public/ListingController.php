<?php

namespace App\Http\Controllers\Public;

use App\Enums\ListingType;
use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicPropertyResource;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'type' => ListingType::tryFrom($this->scalar($request, 'type'))?->value,
            'property_type' => PropertyType::tryFrom($this->scalar($request, 'property_type'))?->value,
            'min_price' => $this->int($request, 'min_price'),
            'max_price' => $this->int($request, 'max_price'),
            'beds' => $this->int($request, 'beds', 20),
            'area' => $this->scalar($request, 'area') ?: null,
            'sort' => in_array($request->query('sort'), ['price_asc', 'price_desc'], true) ? $request->query('sort') : 'newest',
        ];

        $query = Property::query()->publiclyListed()->with(['photos', 'listingAgent:id,name']);

        $filters['type'] && $query->where('listing_type', $filters['type']);
        $filters['property_type'] && $query->where('property_type', $filters['property_type']);
        $filters['area'] && $query->where('area', $filters['area']);
        $filters['min_price'] !== null && $query->where('price', '>=', $filters['min_price']);
        $filters['max_price'] !== null && $query->where('price', '<=', $filters['max_price']);
        $filters['beds'] !== null && $query->where('bedrooms', '>=', $filters['beds']);

        match ($filters['sort']) {
            'price_asc' => $query->orderBy('price')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };

        return Inertia::render('public/Listings/Index', [
            'listings' => PublicPropertyResource::collection($query->paginate(12)->withQueryString()),
            'filters' => $filters,
            'areas' => Property::query()->publiclyListed()->whereNotNull('area')->distinct()->orderBy('area')->pluck('area'),
            'meta' => [
                'title' => 'Homes for sale and rent | Skyline Realty',
                'description' => 'Browse available homes and apartments from Skyline Realty.',
            ],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        // Slug only: the model's numeric-id fallback must not apply to public URLs.
        $property = Property::query()->publiclyListed()
            ->with(['photos', 'listingAgent:id,name'])
            ->where('slug', $slug)
            ->firstOrFail();

        $suffix = $property->listing_type === ListingType::Rent ? '/mo' : '';

        return Inertia::render('public/Listings/Show', [
            'listing' => (new PublicPropertyResource($property))->resolve($request),
            'meta' => [
                'title' => $property->title.' – '.Number::currency($property->price, 'PHP', precision: 0).$suffix.' | Skyline Realty',
                'description' => Str::limit(strip_tags((string) $property->description), 155, ''),
                'image' => url($property->coverUrl()),
                'url' => url('/properties/'.$property->slug),
            ],
        ]);
    }

    private function scalar(Request $request, string $key): string
    {
        $v = $request->query($key);

        return is_string($v) ? trim($v) : '';
    }

    /** Digits only; values above $max are ignored so they always fit the column type. */
    private function int(Request $request, string $key, int $max = 9_999_999_999): ?int
    {
        $v = $this->scalar($request, $key);

        return ctype_digit($v) && strlen($v) <= 10 && (int) $v <= $max ? (int) $v : null;
    }
}
