<?php

return [
    'ai' => [
        'enabled' => filled(env('ANTHROPIC_API_KEY')) && filled(env('VOYAGE_API_KEY')),
        'model' => 'claude-opus-5-5',
        'max_tool_rounds' => 6,
    ],

    'business_hours' => [
        'days' => [1, 2, 3, 4, 5],
        'open' => '09:00',
        'close' => '18:00',
    ],
];
