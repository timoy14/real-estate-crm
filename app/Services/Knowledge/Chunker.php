<?php

namespace App\Services\Knowledge;

class Chunker
{
    /**
     * Split on paragraphs, then sentences, then hard cuts; pack greedily into chunks of at most
     * $maxChars, each (after the first) starting with roughly $overlap chars of the previous one.
     *
     * @return list<string>
     */
    public function split(string $text, int $maxChars = 2000, int $overlap = 200): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) <= $maxChars) {
            return [$text];
        }

        // Each unit must leave room for the overlap tail plus a separator.
        $limit = max(1, $maxChars - $overlap - 2);
        $units = $this->units($text, $limit);

        $chunks = [];
        $current = '';
        foreach ($units as [$sep, $unit]) {
            if ($current === '') {
                $current = $unit;

                continue;
            }
            if (mb_strlen($current) + mb_strlen($sep) + mb_strlen($unit) <= $maxChars) {
                $current .= $sep.$unit;

                continue;
            }
            $chunks[] = $current;
            $tail = $this->tail($current, $overlap);
            $current = $tail === '' ? $unit : $tail.$sep.$unit;
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /** @return list<array{0: string, 1: string}> [separator-before, text] */
    private function units(string $text, int $limit): array
    {
        $units = [];

        foreach (preg_split('/\n\s*\n/u', $text) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            $sep = "\n\n";
            $pieces = mb_strlen($paragraph) <= $limit
                ? [$paragraph]
                : preg_split('/(?<=[.!?])\s+/u', $paragraph, -1, PREG_SPLIT_NO_EMPTY);

            foreach ($pieces as $piece) {
                $piece = trim($piece);
                if ($piece === '') {
                    continue;
                }
                foreach (mb_strlen($piece) > $limit ? mb_str_split($piece, $limit) : [$piece] as $part) {
                    $units[] = [$sep, $part];
                    $sep = ' ';
                }
            }
        }

        return $units;
    }

    private function tail(string $text, int $overlap): string
    {
        if ($overlap <= 0) {
            return '';
        }
        if (mb_strlen($text) <= $overlap) {
            return $text;
        }

        $tail = mb_substr($text, -$overlap);
        // Don't start mid-word if a word boundary is available.
        if (! preg_match('/^\s/u', mb_substr($text, -$overlap - 1, 1)) && preg_match('/^\S*\s+(.*)$/us', $tail, $m) && $m[1] !== '') {
            $tail = $m[1];
        }

        return ltrim($tail);
    }
}
