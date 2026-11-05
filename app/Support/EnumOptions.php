<?php

namespace App\Support;

class EnumOptions
{
    /**
     * @param  class-string<\BackedEnum>  $enum  an enum with label()
     * @return list<array{value: string, label: string}>
     */
    public static function of(string $enum): array
    {
        return array_map(
            fn ($c) => ['value' => $c->value, 'label' => $c->label()],
            $enum::cases(),
        );
    }
}
