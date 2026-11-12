<?php

return [
    'enabled' => env('SHOPPING_ASSISTANT_RESEARCH_ENABLED', false),
    'timeout_seconds' => (int) env('SHOPPING_ASSISTANT_RESEARCH_TIMEOUT_SECONDS', 90),
    'max_output_tokens' => 900,
    // Fail closed until an operator explicitly sets a positive daily request cap.
    // This bounds request count, NOT actual dollars (web_search may have separate fees).
    'max_daily_requests' => (int) env('SHOPPING_ASSISTANT_RESEARCH_MAX_DAILY_REQUESTS', 0),
    // OFF by default: map normalized manufacturer names to independently vetted HTTPS hosts.
    // Example: 'samplebrand' => ['manufacturer.example']; never trust AI-suggested domains.
        'official_domains' => [
        'zwilling' => ['zwilling.com'],
        'wmf' => ['wmf.com', 'wmf.fr'],
        'smeg' => ['smeg.com'],
        'ninja' => ['ninjakitchen.com', 'ninjakitchen.ie', 'sharkninja.com'],
        'kitchenaid' => ['kitchenaid.com'],
        'herdmar' => ['herdmar.com'],
        'kuhn rikon' => ['kuhnrikon.com'],
        'skk' => ['skk-guss.de'],
    ],
];