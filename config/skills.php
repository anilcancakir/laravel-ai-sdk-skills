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
    | Skill Source Mode
    |--------------------------------------------------------------------------
    |
    | Defines where skills are discovered from.
    |
    | 'local':  Only scan local filesystem paths.
    | 'remote': Only fetch skills from a remote API.
    | 'dual':   Merge skills from both local and remote sources.
    |
    */

    'mode' => env('SKILLS_MODE', 'local'),

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

    /*
    |--------------------------------------------------------------------------
    | Remote Discovery
    |--------------------------------------------------------------------------
    |
    | Configuration for fetching skills from a remote API.
    | Only used when mode is 'remote' or 'dual'.
    |
    */

    'remote' => [
        'url' => env('SKILLS_REMOTE_URL'),
        'token' => env('SKILLS_REMOTE_TOKEN'),
        'timeout' => env('SKILLS_REMOTE_TIMEOUT', 5),
    ],
];
