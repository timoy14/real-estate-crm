<?php

use App\Services\Knowledge\Chunker;

it('returns one chunk for short text', function () {
    expect((new Chunker)->split('A short note.'))->toBe(['A short note.']);
});

it('splits 5000 chars of sentences into 3 chunks of at most 2000', function () {
    $text = trim(str_repeat('The quick brown fox jumps. ', 185));
    $chunks = (new Chunker)->split($text);

    expect($chunks)->toHaveCount(3);
    foreach ($chunks as $c) {
        expect(mb_strlen($c))->toBeLessThanOrEqual(2000);
    }
});

it('returns an empty list for blank input', function () {
    expect((new Chunker)->split("  \n\t "))->toBe([]);
});

it('overlaps consecutive chunks', function () {
    $text = implode(' ', array_map(fn ($i) => "Sentence number $i is here.", range(1, 200)));
    $chunks = (new Chunker)->split($text);

    expect(count($chunks))->toBeGreaterThan(1);
    for ($i = 1; $i < count($chunks); $i++) {
        $head = mb_substr($chunks[$i], 0, 20);
        expect(str_contains($chunks[$i - 1], $head))->toBeTrue();
    }
});

it('hard-cuts text with no break points', function () {
    $chunks = (new Chunker)->split(str_repeat('x', 5000));

    expect(count($chunks))->toBeGreaterThan(2);
    foreach ($chunks as $c) {
        expect(mb_strlen($c))->toBeLessThanOrEqual(2000);
    }
});
