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
            return new SkillDiscovery(
                paths: config('skills.paths', [app_path('Skills')]),
                mode: config('skills.mode', 'local'),
                remoteUrl: config('skills.remote.url'),
                remoteToken: config('skills.remote.token'),
                remoteTimeout: config('skills.remote.timeout', 5),
                cacheEnabled: ! $app->environment('local', 'testing'),
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
