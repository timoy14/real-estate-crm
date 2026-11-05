<?php

namespace App\Policies;

use App\Models\Inquiry;
use App\Models\User;
use App\Policies\Concerns\OwnsRecord;

class InquiryPolicy
{
    use OwnsRecord;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Inquiry $inquiry): bool
    {
        return $this->owns($user, $inquiry);
    }

    public function update(User $user, Inquiry $inquiry): bool
    {
        return $this->owns($user, $inquiry);
    }
}
