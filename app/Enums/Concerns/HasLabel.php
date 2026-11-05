<?php

namespace App\Enums\Concerns;

/** label() for backed enums whose label is just the capitalised value. */
trait HasLabel
{
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
