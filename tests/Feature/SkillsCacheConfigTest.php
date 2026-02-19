<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Feature;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillDiscovery;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use ReflectionClass;

class SkillsCacheConfigTest extends TestCase
{
    public function test_missing_cache_config_keeps_legacy_default_behavior()
    {
        $skillsConfig = config('skills');
        unset($skillsConfig['cache']);
        config(['skills' => $skillsConfig]);

        $this->app->forgetInstance(SkillDiscovery::class);
        $discovery = $this->app->make(SkillDiscovery::class);

        $this->assertFalse($this->getProperty($discovery, 'cacheEnabled'));
        $this->assertNull($this->getProperty($discovery, 'cacheStore'));
    }

    public function test_cache_enabled_true_forces_cache_on()
    {
        config(['skills.cache.enabled' => true]);

        $this->app->forgetInstance(SkillDiscovery::class);
        $discovery = $this->app->make(SkillDiscovery::class);

        $this->assertTrue($this->getProperty($discovery, 'cacheEnabled'));
    }

    public function test_cache_enabled_false_forces_cache_off()
    {
        config(['skills.cache.enabled' => false]);

        $this->app->forgetInstance(SkillDiscovery::class);
        $discovery = $this->app->make(SkillDiscovery::class);

        $this->assertFalse($this->getProperty($discovery, 'cacheEnabled'));
    }

    public function test_cache_enabled_invalid_value_falls_back_to_legacy_default()
    {
        config(['skills.cache.enabled' => 'not-a-boolean']);

        $this->app->forgetInstance(SkillDiscovery::class);
        $discovery = $this->app->make(SkillDiscovery::class);

        $this->assertFalse($this->getProperty($discovery, 'cacheEnabled'));
    }

    public function test_cache_store_null_uses_default_store()
    {
        config(['skills.cache.store' => null]);

        $this->app->forgetInstance(SkillDiscovery::class);
        $discovery = $this->app->make(SkillDiscovery::class);

        $this->assertNull($this->getProperty($discovery, 'cacheStore'));
    }

    public function test_cache_store_setting_is_passed_to_discovery()
    {
        config(['skills.cache.store' => 'skills_array']);

        $this->app->forgetInstance(SkillDiscovery::class);
        $discovery = $this->app->make(SkillDiscovery::class);

        $this->assertSame('skills_array', $this->getProperty($discovery, 'cacheStore'));
    }

    private function getProperty(SkillDiscovery $discovery, string $name): mixed
    {
        $reflection = new ReflectionClass($discovery);
        $property = $reflection->getProperty($name);
        $property->setAccessible(true);

        return $property->getValue($discovery);
    }
}
