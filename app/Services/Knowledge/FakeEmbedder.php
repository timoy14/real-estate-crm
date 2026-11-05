<?php

namespace App\Services\Knowledge;

/** Deterministic bag-of-words embedder: texts sharing words come out similar. */
class FakeEmbedder implements Embedder
{
    public const DIMENSIONS = 1024;

    public function embed(array $texts, string $inputType): array
    {
        return array_map(fn (string $t) => $this->vector($t), array_values($texts));
    }

    /** @return list<float> */
    private function vector(string $text): array
    {
        $v = array_fill(0, self::DIMENSIONS, 0.0);
        preg_match_all('/[\pL\pN]+/u', mb_strtolower($text), $m);

        foreach ($m[0] as $word) {
            $v[crc32($word) % self::DIMENSIONS] += 1.0;
        }

        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $v)));
        if ($norm == 0.0) {
            $v[0] = 1.0;

            return $v;
        }

        return array_map(fn ($x) => $x / $norm, $v);
    }
}
