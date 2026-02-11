<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI SDK Skills Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the behavior of your custom skills.
    |
    */

    'enabled' => env('SKILLS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Discovery Mode
    |--------------------------------------------------------------------------
    |
    | Controls how skills are presented to the AI agent at startup.
    |
    | 'lite': Injects name and description only (saves tokens).
    | 'full': Injects name, description, and full instructions.
    |
    */

    'discovery_mode' => env('SKILLS_DISCOVERY_MODE', 'lite'),

    /*
    |--------------------------------------------------------------------------
    | Skill Paths
    |--------------------------------------------------------------------------
    |
    | This array contains the paths where your local skills are located.
    | By default, we look in the resources/skills directory.
    |
    */

    'paths' => [
        resource_path('skills'),
    ],
];
