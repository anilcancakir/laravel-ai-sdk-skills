<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillDiscovery;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class SkillDiscoveryTest extends TestCase
{
    protected string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempPath = __DIR__.'/temp_skills';
        File::ensureDirectoryExists($this->tempPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempPath);
        parent::tearDown();
    }

    public function test_it_discovers_skills_from_configured_paths()
    {
        $this->createSkillFile('path1/skill-one/SKILL.md', 'Skill One');
        $this->createSkillFile('path2/skill-two/SKILL.md', 'Skill Two');

        $discovery = new SkillDiscovery([
            $this->tempPath.'/path1',
            $this->tempPath.'/path2',
        ]);

        $skills = $discovery->discover();

        $this->assertCount(2, $skills);
        $this->assertTrue($skills->has('skill-one'));
        $this->assertTrue($skills->has('skill-two'));
        $this->assertEquals('Skill One', $skills->get('skill-one')->name);
        $this->assertEquals('Skill Two', $skills->get('skill-two')->name);
    }

    public function test_it_overrides_skills_with_same_slug_from_later_paths()
    {
        $this->createSkillFile('path1/common/SKILL.md', 'Common Skill', 'Original Description');
        $this->createSkillFile('path2/common/SKILL.md', 'Common Skill', 'Overridden Description');

        $discovery = new SkillDiscovery([
            $this->tempPath.'/path1',
            $this->tempPath.'/path2',
        ]);

        $skills = $discovery->discover();

        $this->assertCount(1, $skills);
        $this->assertTrue($skills->has('common-skill'));
        $this->assertEquals('Overridden Description', $skills->get('common-skill')->description);
    }

    public function test_it_caches_discovered_skills()
    {
        $this->createSkillFile('path1/cached/SKILL.md', 'Cached Skill');

        $discovery = new SkillDiscovery([$this->tempPath.'/path1'], true, 60);

        $discovery->discover();

        $this->assertTrue(Cache::has('ai_sdk_skills'));

        File::deleteDirectory($this->tempPath.'/path1');

        $skills = $discovery->discover();
        $this->assertTrue($skills->has('cached-skill'));
    }

    public function test_fresh_ignores_cache()
    {
        $this->createSkillFile('path1/fresh/SKILL.md', 'Fresh Skill');

        $discovery = new SkillDiscovery([$this->tempPath.'/path1'], true, 60);
        $discovery->discover();

        File::deleteDirectory($this->tempPath.'/path1/fresh');

        $skills = $discovery->fresh();

        $this->assertCount(0, $skills);
        // fresh() clears and re-discovers, so cache should exist but be empty/updated
        $this->assertTrue(Cache::has('ai_sdk_skills'));
    }

    public function test_it_ignores_invalid_files()
    {
        $path = $this->tempPath.'/path1/invalid/SKILL.md';
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'INVALID CONTENT');

        $discovery = new SkillDiscovery([$this->tempPath.'/path1']);
        $skills = $discovery->discover();

        $this->assertCount(0, $skills);
    }

    protected function createSkillFile($relativePath, $name, $description = 'Desc')
    {
        $path = $this->tempPath.'/'.$relativePath;
        File::ensureDirectoryExists(dirname($path));

        $content = <<<EOT
---
name: {$name}
description: {$description}
tools: []
---
Instructions for {$name}
EOT;
        File::put($path, $content);
    }
}
