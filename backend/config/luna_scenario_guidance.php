<?php

return [
    // Separate explicit opt-in; additionally requires LUNA_SKILLS_ENABLED=true.
    'enabled' => env('LUNA_SCENARIO_GUIDANCE_ENABLED', false),
];
