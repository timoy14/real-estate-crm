<?php

namespace App\Services\Inquiries;

use App\Enums\ContactRole;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Enums\ListingType;
use App\Models\Contact;
use App\Models\User;

class ContactResolver
{
    /**
     * Reuse an existing contact (any owner) matched by email, then phone; otherwise create one.
     * A reused contact keeps its owner.
     */
    public function resolve(string $name, ?string $email, ?string $phone, ListingType $type, User $defaultOwner): Contact
    {
        $role = $type === ListingType::Rent ? ContactRole::Tenant : ContactRole::Buyer;
        $email = filled($email) ? mb_strtolower(trim($email)) : null;
        $normalized = Contact::normalizePhone($phone);

        $contact = null;
        if ($email !== null) {
            $contact = Contact::query()->whereRaw('lower(email) = ?', [$email])->orderBy('id')->first();
        }
        if ($contact === null && $normalized !== null) {
            $contact = Contact::query()->where('phone_normalized', $normalized)->orderBy('id')->first();
        }

        if ($contact === null) {
            [$first, $last] = array_pad(explode(' ', trim($name), 2), 2, '');

            return Contact::create([
                'first_name' => $first,
                'last_name' => trim($last),
                'email' => $email,
                'phone' => $phone,
                'roles' => [$role],
                'status' => ContactStatus::NewLead,
                'source' => ContactSource::Website,
                'owner_id' => $defaultOwner->id,
            ]);
        }

        if (! $contact->roles->contains($role)) {
            $contact->roles = $contact->roles->push($role)->values();
        }
        if (blank($contact->email) && $email !== null) {
            $contact->email = $email;
        }
        if (blank($contact->phone_normalized) && $normalized !== null) {
            $contact->phone = $phone;
        }
        $contact->save();

        return $contact;
    }
}
