<?php

namespace App\Services\Inquiries;

use App\Enums\ActivityType;
use App\Mail\InquiryReceived;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Inquiry;
use App\Models\Pipeline;
use App\Models\Property;
use App\Models\Task;
use App\Notifications\NewInquiryNotification;
use App\Support\BusinessHours;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProcessInquiry
{
    public function __construct(private ContactResolver $contacts) {}

    /**
     * @param  array{name: string, email?: ?string, phone?: ?string, message: string, wants_viewing?: bool, preferred_contact?: ?string}  $data
     */
    public function handle(Property $property, array $data, string $ip, string $ua): Inquiry
    {
        $inquiry = DB::transaction(function () use ($property, $data, $ip, $ua) {
            $property->loadMissing('listingAgent');
            $agent = $property->listingAgent;
            $name = $data['name'];
            $email = $data['email'] ?? null;
            $phone = $data['phone'] ?? null;
            $wantsViewing = (bool) ($data['wants_viewing'] ?? false);

            // Serialize concurrent submissions for the same person so they cannot create duplicate contacts.
            $normalizedEmail = filled($email) ? mb_strtolower(trim($email)) : null;
            $lockKey = $normalizedEmail !== null
                ? 'email:'.$normalizedEmail
                : 'phone:'.Contact::normalizePhone($phone);
            DB::select('select pg_advisory_xact_lock(hashtext(?))', [$lockKey]);

            $contact = $this->contacts->resolve($name, $email, $phone, $property->listing_type, $agent);
            $ownerId = $contact->owner_id;

            $inquiry = Inquiry::create([
                'property_id' => $property->id,
                'contact_id' => $contact->id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'message' => $data['message'],
                'wants_viewing' => $wantsViewing,
                'preferred_contact' => $data['preferred_contact'] ?? 'email',
                'consent' => true,
                'source' => 'website',
                'ip' => $ip,
                'user_agent' => Str::limit($ua, 255, ''),
                'owner_id' => $ownerId,
            ]);

            $pipeline = Pipeline::forListingType($property->listing_type);
            $deal = new Deal([
                'title' => Str::limit($contact->name.' – '.$property->title, 255, ''),
                'pipeline_id' => $pipeline->id,
                'stage_id' => $pipeline->inquiryStage()->id,
                'contact_id' => $contact->id,
                'property_id' => $property->id,
                'value' => $property->price,
                'commission_percent' => Deal::defaultCommission($property->listing_type),
                'owner_id' => $ownerId,
            ]);
            $deal->appendToStage();
            $deal->save();

            $inquiry->update(['deal_id' => $deal->id]);

            $task = new Task([
                'title' => Str::limit("Contact {$name} about {$property->title}", 255, ''),
                'priority' => 'high',
                'due_at' => BusinessHours::addWorkingHour(now()),
                'owner_id' => $ownerId,
                'contact_id' => $contact->id,
                'property_id' => $property->id,
                'deal_id' => $deal->id,
            ]);
            $task->inquiry_id = $inquiry->id;
            $task->save();

            Activity::create([
                'type' => ActivityType::Note,
                'subject' => 'Website inquiry',
                'body' => 'Website inquiry: '.$data['message'].($wantsViewing ? ' Wants a viewing.' : ''),
                'occurred_at' => now(),
                'contact_id' => $contact->id,
                'property_id' => $property->id,
                'deal_id' => $deal->id,
                'owner_id' => $ownerId,
            ]);

            return $inquiry->setRelation('property', $property)->setRelation('contact', $contact);
        });

        // After commit. The inquiry is already saved, so a failing channel or mailer must not turn
        // the visitor's success into a 500; report it and carry on.
        rescue(function () use ($inquiry, $property) {
            $dealOwner = $inquiry->owner;
            $dealOwner->notify(new NewInquiryNotification($inquiry, '/app/clients/'.$inquiry->contact_id));

            $agent = $property->listingAgent;
            if ($agent && $agent->id !== $dealOwner->id) {
                // The agent may not own this client: no visitor details, and link to the property.
                $agent->notify(new NewInquiryNotification(
                    $inquiry,
                    '/app/properties/'.$property->id,
                    'New inquiry on '.$property->title,
                    'A client of '.$dealOwner->name.' inquired about this property.',
                ));
            }
        }, report: true);

        if (filled($inquiry->email)) {
            rescue(fn () => Mail::to($inquiry->email)->queue(new InquiryReceived($inquiry)), report: true);
        }

        return $inquiry;
    }
}
