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

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Discovery Mode
    |--------------------------------------------------------------------------
    |
    | 'lite': Injects name and description only (saves tokens).
    | 'full': Injects name, description, and full instructions.
    |
    */

    'discovery_mode' => 'lite',

    /*
    |--------------------------------------------------------------------------
    | Skill Paths
    |--------------------------------------------------------------------------
    |
    | This array contains the paths where your skills are located.
    | By default, we look in the resources/skills directory.
    |
    */

    'paths' => [
        resource_path('skills'),
    ],
];
