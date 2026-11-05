<?php

namespace App\Events;

use App\Models\Deal;
use App\Models\Stage;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired by Deal::moveTo(). Task 7 listens to write the activity. */
class DealStageChanged
{
    use Dispatchable;

    public function __construct(
        public Deal $deal,
        public Stage $from,
        public Stage $to,
    ) {}
}
