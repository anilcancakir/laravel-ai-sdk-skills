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
    | Default inclusion mode for skills in prompt instructions.
    |
    | This is used when a skill does not declare its own mode in skills():
    | - 'lite' (or alias 'lazy'): injects name and description only.
    | - 'full' (or alias 'eager'): injects full skill instructions.
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

    /*
    |--------------------------------------------------------------------------
    | Declared Skill Enforcement
    |--------------------------------------------------------------------------
    |
    | When enabled, an agent may only load, list and read the skills it declares
    | in its own skills() method. Anything else is reported to the model as if
    | it did not exist, so a prompt injection cannot reach an undeclared skill.
    |
    | Skills are contained to the configured paths either way; this only adds
    | the per-agent allowlist on top.
    |
    */

    'enforce_declared' => env('SKILLS_ENFORCE_DECLARED', false),

    /*
    |--------------------------------------------------------------------------
    | Skill Cache
    |--------------------------------------------------------------------------
    |
    | Configure skill discovery cache behavior.
    |
    | Set "store" to null to use the application's default cache store.
    |
    */

    'cache' => [
        // Config is read during the LoadConfiguration bootstrap, before the "env" container
        // binding exists, so the container's environment() helper is fatal once published.
        'enabled' => env('SKILLS_CACHE_ENABLED', ! in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
        'store' => env('SKILLS_CACHE_STORE', null),
    ],
];
