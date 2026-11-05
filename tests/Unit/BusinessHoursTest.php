<?php

use App\Support\BusinessHours;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

dataset('slots', [
    'Wed 10:00 -> Wed 11:00' => ['2026-11-04 10:00', '2026-11-04 11:00'],
    'Wed 17:30 -> Thu 09:30' => ['2026-11-04 17:30', '2026-11-05 09:30'],
    'Fri 20:00 -> Mon 10:00' => ['2026-11-06 20:00', '2026-11-09 10:00'],
    'Sat 12:00 -> Mon 10:00' => ['2026-11-07 12:00', '2026-11-09 10:00'],
    'Mon 07:00 -> Mon 10:00' => ['2026-11-09 07:00', '2026-11-09 10:00'],
]);

it('adds one working hour', function (string $from, string $expected) {
    $result = BusinessHours::addWorkingHour(CarbonImmutable::parse($from, 'America/New_York'));

    expect($result->setTimezone('America/New_York')->format('Y-m-d H:i'))->toBe($expected);
})->with('slots');

it('converts input from another timezone', function () {
    // 15:00 UTC on Wed Nov 4 is 10:00 in New York.
    $result = BusinessHours::addWorkingHour(CarbonImmutable::parse('2026-11-04 15:00', 'UTC'));

    expect($result->setTimezone('America/New_York')->format('Y-m-d H:i'))->toBe('2026-11-04 11:00');
});
