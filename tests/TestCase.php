<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests;

use AnilcanCakir\LaravelAiSdkSkills\SkillsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SkillsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('skills.paths.project', __DIR__.'/fixtures/skills');
    }
}
