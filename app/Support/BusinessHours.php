<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BusinessHours
{
    /**
     * One working hour after $from, using config('crm.business_hours') in the app timezone.
     * Time that does not fit before closing carries into the next working day's opening.
     */
    public static function addWorkingHour(CarbonInterface $from): CarbonImmutable
    {
        $cfg = config('crm.business_hours');
        $days = $cfg['days'];
        $t = CarbonImmutable::instance($from)->setTimezone(config('app.timezone'));

        $at = fn (CarbonImmutable $day, string $hm) => $day->setTimeFromTimeString($hm);
        $isWorkday = fn (CarbonImmutable $day) => in_array($day->dayOfWeekIso, $days, true);
        $nextOpening = function (CarbonImmutable $day) use ($isWorkday, $at, $cfg): ?CarbonImmutable {
            for ($i = 1; $i <= 14; $i++) {
                $candidate = $day->addDays($i);
                if ($isWorkday($candidate)) {
                    return $at($candidate, $cfg['open']);
                }
            }

            return null;
        };

        if ($isWorkday($t)) {
            $open = $at($t, $cfg['open']);
            $close = $at($t, $cfg['close']);

            if ($t < $open) {
                return $open->addHour();
            }

            if ($t < $close) {
                $remaining = $close->getTimestamp() - $t->getTimestamp();

                if ($remaining >= 3600) {
                    return $t->addHour();
                }

                $next = $nextOpening($t);

                return $next ? $next->addSeconds(3600 - $remaining) : $t->addHour();
            }
        }

        $next = $nextOpening($t);

        return $next ? $next->addHour() : $t->addHour();
    }
}
