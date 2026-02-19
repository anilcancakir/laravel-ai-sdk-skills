<?php

namespace AnilcanCakir\LaravelAiSdkSkills;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillDiscovery;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Register the skills service provider.
 */
class SkillsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/skills.php', 'skills'
        );

        if (! config('skills.enabled', true)) {
            return;
        }

        $this->app->singleton(SkillDiscovery::class, function ($app) {
            $cacheEnabled = filter_var(
                config('skills.cache.enabled'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($cacheEnabled === null) {
                $cacheEnabled = ! $app->environment('local', 'testing');
            }

            $cacheStore = config('skills.cache.store');
            if (! is_string($cacheStore) || trim($cacheStore) === '') {
                $cacheStore = null;
            }

            return new SkillDiscovery(
                paths: config('skills.paths', [resource_path('skills')]),
                cacheEnabled: $cacheEnabled,
                cacheStore: $cacheStore,
            );
        });

        $this->app->scoped(SkillRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/skills.php' => config_path('skills.php'),
            ], 'skills-config');

            $this->commands([
                Console\SkillsListCommand::class,
                Console\SkillsMakeCommand::class,
                Console\SkillsClearCommand::class,
            ]);
        }
    }
}
