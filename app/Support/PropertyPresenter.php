<?php

namespace App\Support;

use App\Models\Property;

class PropertyPresenter
{
    /** The minimal link shape: enough to render a title linking to the property page. */
    public static function ref(Property $p): array
    {
        return ['id' => $p->id, 'slug' => $p->slug, 'title' => $p->title];
    }
}
