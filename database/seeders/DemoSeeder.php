<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\ContactRole;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Enums\DealStatus;
use App\Enums\InquiryStatus;
use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\TaskPriority;
use App\Enums\ViewingStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Inquiry;
use App\Models\Pipeline;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\Stage;
use App\Models\Task;
use App\Models\User;
use App\Models\Wishlist;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Repeatable demo data (seeded random). Run it on a fresh database: it does not de-duplicate.
 * Indexing is suppressed by switching AI off while seeding; the model hooks (slugs etc.) still run.
 */
class DemoSeeder extends Seeder
{
    private const CONTACT_KINDS = ['buyer', 'tenant', 'seller', 'landlord'];

    /** @var array<string, User> */
    private array $agents = [];

    /** @var array<string, array<string, list<Contact>>> owner key => kind => contacts */
    private array $contacts = [];

    /** @var list<Property> */
    private array $properties = [];

    /** @var list<Deal> */
    private array $deals = [];

    /** @var array<string, array<string, Stage>> */
    private array $stages = [];

    /** @var array<string, mixed> */
    private array $pools = [];

    /** @var array<string, string> */
    private array $photoCache = [];

    private int $contactCounter = 0;

    public function run(): void
    {
        mt_srand(2026);
        fake()->seed(2026);

        $aiWas = config('crm.ai.enabled');
        config(['crm.ai.enabled' => false]);

        try {
            $this->load();
            $this->seedContacts();
            $this->seedProperties();
            $this->seedPhotos();
            $this->seedWishlists();
            $this->seedDeals();
            $this->seedInquiries();
            $this->seedViewings();
            $this->seedActivities();
            $this->seedTasks();
        } finally {
            config(['crm.ai.enabled' => $aiWas]);
        }

        if (config('crm.ai.enabled')) {
            Artisan::call('knowledge:rebuild', [], $this->command?->getOutput());
        } else {
            $this->command?->getOutput()->writeln('AI disabled: skipped indexing.');
        }
    }

    // ---------------------------------------------------------------- setup

    private function load(): void
    {
        $dir = __DIR__.'/demo/';
        foreach (['areas', 'streets', 'names', 'notes', 'descriptions'] as $name) {
            $this->pools[$name] = require $dir.$name.'.php';
        }

        if (User::query()->where('email', 'anna@demo.test')->doesntExist()) {
            $this->call(UserSeeder::class);
        }
        if (Pipeline::query()->doesntExist()) {
            $this->call(PipelineSeeder::class);
        }

        foreach (['admin', 'anna', 'ben'] as $key) {
            $this->agents[$key] = User::query()->where('email', $key.'@demo.test')->firstOrFail();
        }
        foreach (Pipeline::with('stages')->get() as $pipeline) {
            foreach ($pipeline->stages as $stage) {
                $this->stages[$pipeline->slug][$stage->name] = $stage;
            }
        }
    }

    // ------------------------------------------------------------- helpers

    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    private function sample(array $items, int $n): array
    {
        $items = array_values($items);
        $this->shuffle($items);

        return array_slice($items, 0, $n);
    }

    /** Fisher-Yates driven by mt_rand, so seeding stays repeatable. */
    private function shuffle(array &$items): void
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }

    private function chance(int $percent): bool
    {
        return mt_rand(1, 100) <= $percent;
    }

    /** A working-hours moment $days ago (negative: in the future), in the app timezone. */
    private function daysAgo(int $days, ?int $hour = null, ?int $minute = null): CarbonInterface
    {
        return now()->startOfDay()->subDays($days)
            ->setTime($hour ?? mt_rand(9, 17), $minute ?? $this->pick([0, 15, 30, 45]));
    }

    /** Callers drop {property} templates when there is no property. */
    private function fill(string $template, Contact $contact, ?Property $property = null): string
    {
        return strtr($template, ['{name}' => $contact->first_name] + ($property ? ['{property}' => $property->title] : []));
    }

    /** @return list<Contact> */
    private function ownerContacts(string $ownerKey, array $kinds = self::CONTACT_KINDS): array
    {
        return array_merge(...array_map(fn ($k) => $this->contacts[$ownerKey][$k] ?? [], $kinds));
    }

    /** @return list<Contact> */
    private function allContacts(array $kinds = self::CONTACT_KINDS): array
    {
        return array_merge(...array_map(fn ($key) => $this->ownerContacts($key, $kinds), array_keys($this->agents)));
    }

    private function ownerKey(int $userId): string
    {
        return array_search($userId, array_map(fn ($u) => $u->id, $this->agents), true);
    }

    private function stamp(Model $model, CarbonInterface $at): void
    {
        $model->forceFill(['created_at' => $at, 'updated_at' => $at]);
    }

    // ------------------------------------------------------------ contacts

    private function seedContacts(): void
    {
        // Per agent: 70% buyers/tenants (30% of those tenants), the rest sellers/landlords.
        foreach (['anna' => 50, 'ben' => 50, 'admin' => 20] as $key => $total) {
            $seekers = (int) round($total * 0.7);
            for ($i = 0; $i < $total; $i++) {
                $kind = $i < $seekers
                    ? ($i % 10 < 7 ? 'buyer' : 'tenant')
                    : ($i % 2 === 0 ? 'seller' : 'landlord');
                $this->contacts[$key][$kind][] = $this->makeContact($key, $kind);
            }
        }
    }

    private function makeContact(string $ownerKey, string $kind): Contact
    {
        $names = $this->pools['names'];
        $first = $this->pick($names['first']);
        $last = $this->pick($names['last']);
        $n = ++$this->contactCounter;
        $seeker = in_array($kind, ['buyer', 'tenant'], true);

        $contact = new Contact([
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower("$first.$last.$n@example.com"),
            'phone' => sprintf('(212) 555-%04d', $n),
            'roles' => [ContactRole::from($kind)],
            'status' => $seeker
                ? $this->pick([ContactStatus::NewLead, ContactStatus::Active, ContactStatus::Active, ContactStatus::Closed, ContactStatus::Lost])
                : ContactStatus::Active,
            'source' => $this->pick(ContactSource::cases()),
            'tags' => $this->sample($names['tags'], mt_rand(0, 2)),
            'notes' => $this->chance(40) ? "Prefers contact by {$this->pick(['phone', 'email', 'text'])}." : null,
            'owner_id' => $this->agents[$ownerKey]->id,
        ]);
        $this->stamp($contact, $this->daysAgo(mt_rand(95, 200)));
        $contact->save();

        return $contact;
    }

    // ---------------------------------------------------------- properties

    private function seedProperties(): void
    {
        // [listing type, status, published]: 50 available + published, 10 spread over the other statuses.
        $specs = [];
        $add = function (string $type, string $status, bool $published, int $n) use (&$specs) {
            array_push($specs, ...array_fill(0, $n, [ListingType::from($type), PropertyStatus::from($status), $published]));
        };
        $add('sale', 'available', true, 34);
        $add('sale', 'draft', false, 1);
        $add('sale', 'under_offer', true, 2);
        $add('sale', 'sold', true, 2);
        $add('sale', 'withdrawn', false, 1);
        $add('rent', 'available', true, 16);
        $add('rent', 'draft', false, 1);
        $add('rent', 'under_offer', true, 1);
        $add('rent', 'rented', true, 2);
        $this->shuffle($specs);

        $areas = array_keys($this->pools['areas']);
        $agentKeys = ['anna', 'ben', 'admin'];
        $hidden = 0;

        foreach ($specs as $i => [$listing, $status, $published]) {
            $areaName = $areas[$i % count($areas)];
            [$lat, $lng, $areaFactor] = $this->pools['areas'][$areaName];
            $type = $listing === ListingType::Rent
                ? $this->pick([PropertyType::Apartment, PropertyType::Apartment, PropertyType::Condo, PropertyType::House])
                : $this->pick([PropertyType::House, PropertyType::House, PropertyType::House, PropertyType::House, PropertyType::Apartment,
                    PropertyType::Apartment, PropertyType::Condo, PropertyType::Condo, PropertyType::Land, PropertyType::Commercial]);
            $beds = match ($type) {
                PropertyType::Land, PropertyType::Commercial => null,
                PropertyType::House => mt_rand(2, 5),
                default => mt_rand(1, 3),
            };
            $agentKey = $agentKeys[$i % 3];
            $ownerKind = $listing === ListingType::Rent ? 'landlord' : 'seller';
            $hide = $status === PropertyStatus::Available && $hidden < 6 && $i % 5 === 0 && ++$hidden;

            $property = new Property([
                'title' => $this->title($type, $beds, $areaName),
                'address' => mt_rand(10, 480).' '.$this->pick($this->pools['streets']),
                'area' => $areaName,
                'city' => 'Brookfield',
                'latitude' => $this->chance(90) ? round($lat + (mt_rand(-300, 300) / 10000), 7) : null,
                'longitude' => $this->chance(90) ? round($lng + (mt_rand(-300, 300) / 10000), 7) : null,
                'listing_type' => $listing,
                'property_type' => $type,
                'price' => $this->price($listing, $type, $beds, $areaFactor),
                'bedrooms' => $beds,
                'bathrooms' => $beds === null ? null : max(1, min(4, (int) ceil($beds / 2) + mt_rand(0, 1))),
                'floor_area_sqft' => $type === PropertyType::Land ? null : ($beds ?? 4) * mt_rand(350, 550) + 200,
                'lot_area_sqft' => in_array($type, [PropertyType::House, PropertyType::Land], true) ? mt_rand(2500, 12000) : null,
                'year_built' => $type === PropertyType::Land ? null : mt_rand(1925, 2024),
                'features' => $this->sample(Property::FEATURES, mt_rand(2, 6)),
                'status' => $status,
                'is_published' => $published,
                'hide_exact_address' => (bool) $hide,
                'owner_contact_id' => $this->pick($this->contacts[$agentKey][$ownerKind])->id,
                'listing_agent_id' => $this->agents[$agentKey]->id,
            ]);
            $pool = match ($type) {
                PropertyType::Land => 'land',
                PropertyType::Commercial => 'commercial',
                default => 'home',
            };
            $property->description = strtr($this->pick($this->pools['descriptions'][$pool]), [
                '{bedrooms}' => (string) $beds,
                '{area}' => $areaName,
                '{type}' => strtolower($type->label()),
                '{year}' => (string) ($property->year_built ?? 2000),
                '{lot}' => number_format($property->lot_area_sqft ?? 0),
                '{sqft}' => number_format($property->floor_area_sqft ?? 0),
            ]);
            $this->stamp($property, $this->daysAgo(mt_rand(100, 200)));
            $property->save();
            $this->properties[] = $property;
        }
    }

    private function title(PropertyType $type, ?int $beds, string $area): string
    {
        $adj = $this->pick(['Bright', 'Modern', 'Charming', 'Spacious', 'Elegant', 'Cozy', 'Renovated', 'Sunny']);

        return match ($type) {
            PropertyType::Land => "Building Plot in $area",
            PropertyType::Commercial => "$adj Commercial Unit in $area",
            default => "$adj $beds-Bedroom {$type->label()} in $area",
        };
    }

    /** Grows with bedrooms inside a type; the area factor scales everything; ±3% noise stays below one bedroom step. */
    private function price(ListingType $listing, PropertyType $type, ?int $beds, float $areaFactor): int
    {
        $noise = 0.97 + mt_rand(0, 60) / 1000;
        $bedFactor = 1 + 0.28 * (($beds ?? 1) - 1);

        if ($listing === ListingType::Rent) {
            $base = match ($type) {
                PropertyType::House => 95000, PropertyType::Condo => 75000, default => 65000,
            };

            return (int) (round($base * $bedFactor * $areaFactor * $noise / 1000) * 1000);
        }

        $base = match ($type) {
            PropertyType::House => 16500000, PropertyType::Apartment => 11000000, PropertyType::Condo => 13000000,
            PropertyType::Land => 7500000, PropertyType::Commercial => 30000000,
        };

        return (int) (round($base * $bedFactor * $areaFactor * $noise / 100000) * 100000);
    }

    // -------------------------------------------------------------- photos

    private function seedPhotos(): void
    {
        $disk = Storage::disk('public');
        $real = glob(__DIR__.'/photos/*.jpg') ?: [];

        foreach ($this->properties as $property) {
            $count = mt_rand(3, 6);
            foreach (range(0, $count - 1) as $position) {
                $path = "properties/{$property->id}/photo-".($position + 1).'.jpg';
                $bytes = $real !== []
                    ? File::get($this->pick($real))
                    : $this->generatedPhoto($property->property_type, $position);
                $disk->put($path, $bytes);
                PropertyPhoto::create(['property_id' => $property->id, 'path' => $path, 'position' => $position]);
            }
        }
    }

    /** Soft sky-gradient 1200x800 JPEG with the property type; cached per type and variant. */
    private function generatedPhoto(PropertyType $type, int $variant): string
    {
        $key = $type->value.$variant;
        if (isset($this->photoCache[$key])) {
            return $this->photoCache[$key];
        }

        [$w, $h] = [1200, 800];
        $img = imagecreatetruecolor($w, $h);
        $shift = $variant * 6;
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $color = imagecolorallocate(
                $img,
                (int) (150 + 85 * $t) - $shift,
                (int) (200 + 40 * $t) - $shift,
                (int) (245 + 5 * $t),
            );
            imageline($img, 0, $y, $w, $y, $color);
        }

        // The built-in GD font is tiny, so draw the label small and scale it up.
        $label = strtoupper($type->label());
        $small = imagecreatetruecolor(160, 24);
        imagefill($small, 0, 0, imagecolorallocate($small, 20, 70, 120));
        imagecolortransparent($small, imagecolorallocate($small, 20, 70, 120));
        imagestring($small, 5, 2, 4, $label, imagecolorallocate($small, 255, 255, 255));
        $scale = 4;
        imagecopyresized($img, $small, 60, $h - 180, 0, 0, 160 * $scale, 24 * $scale, 160, 24);
        imagestring($img, 5, 62, $h - 60, 'Photo '.($variant + 1).' - Skyline Realty demo', imagecolorallocate($img, 255, 255, 255));

        ob_start();
        imagejpeg($img, null, 82);
        $bytes = (string) ob_get_clean();

        return $this->photoCache[$key] = $bytes;
    }

    // ------------------------------------------------------------ wishlists

    private function seedWishlists(): void
    {
        $areas = array_keys($this->pools['areas']);

        foreach ($this->allContacts(['buyer', 'tenant']) as $contact) {
            $rent = $contact->roles->contains(ContactRole::Tenant);
            $budget = $rent ? mt_rand(12, 45) * 5000 : mt_rand(25, 150) * 500000;

            Wishlist::create([
                'contact_id' => $contact->id,
                'listing_type' => $rent ? ListingType::Rent : ListingType::Sale,
                'property_types' => array_map(fn ($t) => $t->value, $this->sample(
                    $rent ? [PropertyType::Apartment, PropertyType::Condo, PropertyType::House] : [PropertyType::House, PropertyType::Apartment, PropertyType::Condo],
                    mt_rand(1, 2),
                )),
                'min_budget' => (int) round($budget * 0.6 / 100) * 100,
                'max_budget' => $budget,
                'min_bedrooms' => mt_rand(1, 4),
                'min_bathrooms' => mt_rand(1, 3),
                'areas' => $this->sample($areas, mt_rand(1, 3)),
                'must_have_features' => $this->sample(Property::FEATURES, mt_rand(0, 3)),
                'move_in_date' => $this->chance(60) ? now()->addDays(mt_rand(14, 150))->toDateString() : null,
                'notes' => null,
            ]);
        }
    }

    // --------------------------------------------------------------- deals

    private function seedDeals(): void
    {
        // [pipeline slug, stage name, count]: 70 in total, every stage covered.
        $plan = [
            ['sales', 'Inquiry', 9], ['sales', 'Viewing', 9], ['sales', 'Offer', 8], ['sales', 'Negotiation', 7],
            ['sales', 'Closed Won', 7], ['sales', 'Lost', 5],
            ['rentals', 'Inquiry', 6], ['rentals', 'Viewing', 6], ['rentals', 'Application', 5],
            ['rentals', 'Lease Signed', 4], ['rentals', 'Lost', 4],
        ];

        foreach ($plan as [$slug, $stageName, $count]) {
            $stage = $this->stages[$slug][$stageName];
            $listing = $slug === 'rentals' ? ListingType::Rent : ListingType::Sale;
            $seekers = $this->allContacts([$listing === ListingType::Rent ? 'tenant' : 'buyer']);
            $matching = array_values(array_filter($this->properties, fn ($p) => $p->listing_type === $listing));
            $closedStatuses = $listing === ListingType::Rent ? [PropertyStatus::Rented] : [PropertyStatus::Sold];
            $wonProps = array_values(array_filter($matching, fn ($p) => in_array($p->status, $closedStatuses, true)));
            $liveProps = array_values(array_filter($matching, fn ($p) => $p->status === PropertyStatus::Available));

            for ($i = 0; $i < $count; $i++) {
                $contact = $this->pick($seekers);
                $property = $stage->is_won && $wonProps ? $this->pick($wonProps) : $this->pick($liveProps);

                $deal = new Deal([
                    'title' => "{$contact->name} – {$property->title}",
                    'pipeline_id' => $stage->pipeline_id,
                    'stage_id' => $stage->id,
                    'contact_id' => $contact->id,
                    'property_id' => $property->id,
                    'value' => $property->price,
                    'commission_percent' => Deal::defaultCommission($listing),
                    'owner_id' => $contact->owner_id,
                    'status' => DealStatus::Open,
                ]);
                $deal->appendToStage();

                if ($stage->is_won || $stage->is_lost) {
                    $closed = $this->daysAgo(mt_rand(3, 60));
                    $deal->status = $stage->is_won ? DealStatus::Won : DealStatus::Lost;
                    $deal->closed_at = $closed;
                    $deal->lost_reason = $stage->is_lost ? $this->pick($this->pools['notes']['lost_reasons']) : null;
                    $created = $closed->copy()->subDays(mt_rand(10, 30));
                } else {
                    $deal->expected_close_date = now()->addDays(mt_rand(10, 90))->toDateString();
                    // Ten Inquiry deals (6 sales, 4 rentals) are fresh: they back the "new" inquiries.
                    $created = $stageName === 'Inquiry'
                        ? $this->daysAgo($i < ($slug === 'sales' ? 6 : 4) ? mt_rand(0, 4) : mt_rand(5, 10))
                        : $this->daysAgo(mt_rand(8, 80));
                }
                $this->stamp($deal, $created);
                $deal->save();
                $this->deals[] = $deal;
            }
        }
    }

    // ----------------------------------------------------------- inquiries

    private function seedInquiries(): void
    {
        $by = fn (callable $f) => array_values(array_filter($this->deals, $f));
        $stageOf = fn (Deal $d) => $d->stage_id;
        $inquiryStageIds = [$this->stages['sales']['Inquiry']->id, $this->stages['rentals']['Inquiry']->id];
        $wonIds = [$this->stages['sales']['Closed Won']->id, $this->stages['rentals']['Lease Signed']->id];
        $midIds = [
            $this->stages['sales']['Viewing']->id, $this->stages['sales']['Offer']->id, $this->stages['sales']['Negotiation']->id,
            $this->stages['rentals']['Viewing']->id, $this->stages['rentals']['Application']->id,
        ];

        $fresh = now()->startOfDay()->subDays(4);
        $new = array_slice($by(fn ($d) => in_array($stageOf($d), $inquiryStageIds, true) && $d->created_at->gte($fresh)), 0, 10);
        $converted = array_slice($by(fn ($d) => in_array($stageOf($d), $wonIds, true)), 0, 4);
        $contacted = array_slice($by(fn ($d) => in_array($stageOf($d), $midIds, true)), 0, 11);

        $rows = [];
        foreach ($new as $d) {
            $rows[] = [$d, InquiryStatus::New];
        }
        foreach ($contacted as $d) {
            $rows[] = [$d, InquiryStatus::Contacted];
        }
        foreach ($converted as $d) {
            $rows[] = [$d, InquiryStatus::Converted];
        }

        foreach ($rows as [$deal, $status]) {
            $contact = Contact::query()->find($deal->contact_id);
            $property = Property::query()->find($deal->property_id);
            // Fresh inquiries are recent; handled ones predate the deal's current stage.
            $at = $status === InquiryStatus::New ? $deal->created_at : $deal->created_at->copy();
            $wantsViewing = $this->chance(50);
            $message = $this->pick($this->pools['notes']['inquiry_messages']);

            $inquiry = new Inquiry([
                'property_id' => $property->id,
                'contact_id' => $contact->id,
                'deal_id' => $deal->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'message' => $message,
                'wants_viewing' => $wantsViewing,
                'preferred_contact' => $this->pick(['email', 'phone']),
                'consent' => true,
                'source' => 'website',
                'ip' => '203.0.113.'.mt_rand(1, 250),
                'user_agent' => 'Mozilla/5.0 (demo seed)',
                'status' => $status,
                'owner_id' => $deal->owner_id,
            ]);
            $this->stamp($inquiry, $at);
            $inquiry->save();

            $task = new Task([
                'title' => "Contact {$contact->name} about {$property->title}",
                'priority' => TaskPriority::High,
                'due_at' => $at->copy()->addHour(),
                'completed_at' => $status === InquiryStatus::New ? null : $at->copy()->addHours(mt_rand(1, 5)),
                'owner_id' => $deal->owner_id,
                'contact_id' => $contact->id,
                'property_id' => $property->id,
                'deal_id' => $deal->id,
            ]);
            $task->inquiry_id = $inquiry->id;
            $this->stamp($task, $at);
            $task->save();

            $note = new Activity([
                'type' => ActivityType::Note,
                'subject' => 'Website inquiry',
                'body' => 'Website inquiry: '.$message.($wantsViewing ? ' Wants a viewing.' : ''),
                'occurred_at' => $at,
                'contact_id' => $contact->id,
                'property_id' => $property->id,
                'deal_id' => $deal->id,
                'owner_id' => $deal->owner_id,
            ]);
            $this->stamp($note, $at);
            $note->save();
        }
    }

    // ------------------------------------------------------------ viewings

    private function seedViewings(): void
    {
        $available = array_values(array_filter($this->properties, fn ($p) => $p->status === PropertyStatus::Available));

        // 80 completed viewings over the past 90 days.
        for ($i = 0; $i < 80; $i++) {
            $deal = $this->chance(50) ? $this->pick($this->deals) : null;
            $contact = $deal ? Contact::query()->find($deal->contact_id) : $this->pick($this->allContacts(['buyer', 'tenant']));
            $property = $deal ? Property::query()->find($deal->property_id) : $this->pick($available);
            $at = $this->daysAgo(mt_rand(1, 90));

            $this->viewing($contact, $property, $deal, $at, ViewingStatus::Completed);
        }

        // 10 scheduled viewings in the next 3 days: 2 today for anna.
        // [agent, days from today, hour, minute]
        $upcoming = [
            ['anna', 0, 11, 0], ['anna', 0, 15, 30], ['ben', 0, 14, 0],
            ['anna', 1, 10, 0], ['anna', 1, 16, 0], ['ben', 1, 9, 30], ['ben', 1, 13, 0],
            ['admin', 2, 11, 30], ['anna', 2, 14, 30], ['ben', 3, 10, 30],
        ];
        foreach ($upcoming as [$key, $plus, $hour, $minute]) {
            $contact = $this->pick($this->ownerContacts($key, ['buyer', 'tenant']));
            $property = $this->pick($available);
            $at = now()->startOfDay()->addDays($plus)->setTime($hour, $minute);

            $this->viewing($contact, $property, null, $at, ViewingStatus::Scheduled);
        }
    }

    private function viewing(Contact $contact, Property $property, ?Deal $deal, CarbonInterface $at, ViewingStatus $status): void
    {
        $completed = $status === ViewingStatus::Completed;
        $activity = new Activity([
            'type' => ActivityType::Viewing,
            'subject' => 'Viewing: '.$property->title,
            'body' => null,
            'occurred_at' => $completed ? $at : null,
            'scheduled_at' => $at,
            'duration_minutes' => 30,
            'viewing_status' => $status,
            'feedback' => $completed ? $this->pick($this->pools['notes']['feedback']) : null,
            'contact_id' => $contact->id,
            'property_id' => $property->id,
            'deal_id' => $deal?->id,
            'owner_id' => $contact->owner_id,
        ]);
        $this->stamp($activity, $completed ? $at : now()->subDays(mt_rand(1, 3)));
        $activity->save();
    }

    // ---------------------------------------------------------- activities

    private function seedActivities(): void
    {
        // 400 in total: 80 + 10 viewings and 25 inquiry notes already exist; the rest are calls, emails, meetings, notes.
        $remaining = 400 - Activity::query()->count();
        $contacts = $this->allContacts();
        $dealsByContact = [];
        foreach ($this->deals as $deal) {
            $dealsByContact[$deal->contact_id][] = $deal;
        }
        $types = ['call', 'call', 'call', 'call', 'email', 'email', 'email', 'meeting', 'meeting', 'note', 'note', 'note'];

        for ($i = 0; $i < $remaining; $i++) {
            $contact = $this->pick($contacts);
            $deal = isset($dealsByContact[$contact->id]) && $this->chance(60) ? $this->pick($dealsByContact[$contact->id]) : null;
            $property = $deal
                ? Property::query()->find($deal->property_id)
                : ($this->chance(35) ? $this->pick($this->properties) : null);
            $type = $this->pick($types);
            $at = $this->daysAgo(mt_rand(1, 90));
            $templates = $this->pools['notes'][$type];
            if (! $property) {
                $templates = array_values(array_filter($templates, fn ($t) => ! str_contains($t, '{property}')));
            }
            $body = $this->fill($this->pick($templates), $contact, $property);

            $activity = new Activity([
                'type' => ActivityType::from($type),
                'subject' => $type === 'note' ? null : ucfirst($type).' with '.$contact->name,
                'body' => $body,
                'occurred_at' => $at,
                'duration_minutes' => in_array($type, ['call', 'meeting'], true) ? $this->pick([5, 10, 15, 30, 45, 60]) : null,
                'call_outcome' => $type === 'call' ? $this->pick(Activity::CALL_OUTCOMES) : null,
                'contact_id' => $contact->id,
                'property_id' => $property?->id,
                'deal_id' => $deal?->id,
                'owner_id' => $contact->owner_id,
            ]);
            $this->stamp($activity, $at);
            $activity->save();
        }
    }

    // --------------------------------------------------------------- tasks

    private function seedTasks(): void
    {
        $remaining = 100 - Task::query()->count();
        $dealsByContact = [];
        foreach ($this->deals as $deal) {
            $dealsByContact[$deal->contact_id][] = $deal;
        }
        $perAgent = ['anna' => 0.4, 'ben' => 0.4, 'admin' => 0.2];

        $made = 0;
        foreach ($perAgent as $key => $share) {
            $n = $key === 'admin' ? $remaining - $made : (int) round($remaining * $share);
            for ($i = 0; $i < $n; $i++, $made++) {
                $contact = $this->pick($this->ownerContacts($key, ['buyer', 'tenant', 'seller', 'landlord']));
                $deal = isset($dealsByContact[$contact->id]) && $this->chance(50) ? $this->pick($dealsByContact[$contact->id]) : null;
                $bucket = $i % 20;
                $completedAt = null;

                if ($bucket < 5) {                   // overdue
                    $due = $this->daysAgo(mt_rand(1, 9));
                } elseif ($bucket < 9) {             // due today
                    $due = now()->startOfDay()->setTime(mt_rand(9, 17), $this->pick([0, 30]));
                } elseif ($bucket < 16) {            // upcoming
                    $due = $this->daysAgo(-mt_rand(1, 14));
                } elseif ($bucket < 17) {            // no due date
                    $due = null;
                } else {                             // completed
                    $due = $this->daysAgo(mt_rand(1, 20));
                    $completedAt = $due->copy()->subHours(mt_rand(1, 20));
                }

                $task = new Task([
                    'title' => $this->fill($this->pick($this->pools['notes']['task_titles']), $contact),
                    'due_at' => $due,
                    'priority' => $this->pick(TaskPriority::cases()),
                    'completed_at' => $completedAt,
                    'owner_id' => $contact->owner_id,
                    'contact_id' => $contact->id,
                    'deal_id' => $deal?->id,
                    'property_id' => $deal?->property_id,
                ]);
                $this->stamp($task, $this->daysAgo(mt_rand(1, 25)));
                $task->save();
            }
        }
    }
}
