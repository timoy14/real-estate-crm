<?php

namespace App\Services\Knowledge;

interface Embedder
{
    /**
     * @param  list<string>  $texts
     * @param  'document'|'query'  $inputType
     * @return list<list<float>>
     *
     * @throws EmbeddingFailed
     */
    public function embed(array $texts, string $inputType): array;
}
