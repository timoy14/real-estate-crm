<?php

namespace App\Listeners;

use App\Enums\ActivityType;
use App\Events\DealStageChanged;
use App\Models\Activity;

class LogDealStageChange
{
    public function handle(DealStageChanged $event): void
    {
        $deal = $event->deal;

        Activity::create([
            'type' => ActivityType::Note,
            'subject' => 'Moved to '.$event->to->name,
            'occurred_at' => now(),
            'deal_id' => $deal->id,
            'contact_id' => $deal->contact_id,
            'owner_id' => auth()->id() ?? $deal->owner_id,
        ]);
    }
}
