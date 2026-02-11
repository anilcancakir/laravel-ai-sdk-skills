<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillDiscovery;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $this->assertEquals('local', $skills->get('skill-one')->source);
    }

    public function test_it_discovers_skills_from_remote_source()
    {
        Http::fake([
            'https://example.com/skills' => Http::response([
                'skills' => [
                    [
                        'name' => 'Remote Skill',
                        'description' => 'Remote Description',
                        'instructions' => 'Remote Instructions',
                    ],
                ],
            ], 200),
        ]);

        $discovery = new SkillDiscovery([], 'remote', 'https://example.com/skills');

        $skills = $discovery->discover();

        $this->assertCount(1, $skills);
        $this->assertTrue($skills->has('remote-skill'));
        $this->assertEquals('Remote Skill', $skills->get('remote-skill')->name);
        $this->assertEquals('remote', $skills->get('remote-skill')->source);
    }

    public function test_it_discovers_skills_in_dual_mode()
    {
        $this->createSkillFile('path1/local-skill/SKILL.md', 'Local Skill');

        Http::fake([
            'https://example.com/skills' => Http::response([
                'skills' => [
                    [
                        'name' => 'Remote Skill',
                        'description' => 'Remote Description',
                        'instructions' => 'Remote Instructions',
                    ],
                ],
            ], 200),
        ]);

        $discovery = new SkillDiscovery([$this->tempPath.'/path1'], 'dual', 'https://example.com/skills');

        $skills = $discovery->discover();

        $this->assertCount(2, $skills);
        $this->assertTrue($skills->has('local-skill'));
        $this->assertTrue($skills->has('remote-skill'));
        $this->assertEquals('local', $skills->get('local-skill')->source);
        $this->assertEquals('remote', $skills->get('remote-skill')->source);
    }

    public function test_remote_skills_can_be_parsed_from_raw_content()
    {
        Http::fake([
            'https://example.com/skills' => Http::response([
                'skills' => [
                    [
                        'content' => "---\nname: Raw Remote\ndescription: Raw Desc\n---\nRaw Body",
                    ],
                ],
            ], 200),
        ]);

        $discovery = new SkillDiscovery([], 'remote', 'https://example.com/skills');

        $skills = $discovery->discover();

        $this->assertCount(1, $skills);
        $this->assertTrue($skills->has('raw-remote'));
        $this->assertEquals('Raw Remote', $skills->get('raw-remote')->name);
        $this->assertEquals('remote', $skills->get('raw-remote')->source);
        $this->assertEquals('Raw Body', $skills->get('raw-remote')->instructions);
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

        $discovery = new SkillDiscovery(
            paths: [$this->tempPath.'/path1'],
            cacheEnabled: true,
            cacheTtl: 60
        );

        $discovery->discover();

        $this->assertTrue(Cache::has('ai_sdk_skills'));

        File::deleteDirectory($this->tempPath.'/path1');

        $skills = $discovery->discover();
        $this->assertTrue($skills->has('cached-skill'));
    }

    public function test_fresh_ignores_cache()
    {
        $this->createSkillFile('path1/fresh/SKILL.md', 'Fresh Skill');

        $discovery = new SkillDiscovery(
            paths: [$this->tempPath.'/path1'],
            cacheEnabled: true,
            cacheTtl: 60
        );
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

    public function test_remote_returns_empty_on_http_failure()
    {
        Http::fake([
            'https://example.com/skills' => Http::response([], 500),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message) => str_contains($message, '500'));

        $discovery = new SkillDiscovery([], 'remote', 'https://example.com/skills');

        $skills = $discovery->discover();

        $this->assertCount(0, $skills);
    }

    public function test_remote_returns_empty_on_connection_error()
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'timeout'));

        $discovery = new SkillDiscovery([], 'remote', 'https://example.com/skills');

        $skills = $discovery->discover();

        $this->assertCount(0, $skills);
    }

    public function test_remote_returns_empty_when_url_is_empty()
    {
        Http::fake();

        $discovery = new SkillDiscovery([], 'remote', null);

        $skills = $discovery->discover();

        $this->assertCount(0, $skills);
        Http::assertNothingSent();
    }

    public function test_remote_sends_bearer_token()
    {
        Http::fake([
            'https://example.com/skills' => Http::response([
                'skills' => [],
            ], 200),
        ]);

        $discovery = new SkillDiscovery([], 'remote', 'https://example.com/skills', 'secret-token');

        $discovery->discover();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_clear_cache_removes_cached_skills()
    {
        $this->createSkillFile('path1/cached/SKILL.md', 'Cached Skill');

        $discovery = new SkillDiscovery(
            paths: [$this->tempPath.'/path1'],
            cacheEnabled: true,
            cacheTtl: 60
        );

        $discovery->discover();
        $this->assertTrue(Cache::has('ai_sdk_skills'));

        $discovery->clearCache();
        $this->assertFalse(Cache::has('ai_sdk_skills'));
    }

    public function test_clear_cache_is_noop_when_cache_disabled()
    {
        Cache::spy();

        $discovery = new SkillDiscovery(
            paths: [],
            cacheEnabled: false,
        );

        $discovery->clearCache();

        Cache::shouldNotHaveReceived('forget');
    }

    public function test_resolve_by_name_returns_skill_from_discover()
    {
        $this->createSkillFile('path1/my-skill/SKILL.md', 'My Skill');

        $discovery = new SkillDiscovery(
            paths: [$this->tempPath.'/path1'],
            cacheEnabled: false,
        );

        $skill = $discovery->resolve('my-skill');

        $this->assertNotNull($skill);
        $this->assertEquals('My Skill', $skill->name);
    }

    public function test_resolve_by_directory_path()
    {
        $this->createSkillFile('path1/dir-skill/SKILL.md', 'Dir Skill');

        $discovery = new SkillDiscovery(
            paths: [$this->tempPath.'/path1'],
            cacheEnabled: false,
        );

        $skill = $discovery->resolve($this->tempPath.'/path1/dir-skill');

        $this->assertNotNull($skill);
        $this->assertEquals('Dir Skill', $skill->name);
        $this->assertEquals($this->tempPath.'/path1/dir-skill', $skill->basePath);
    }

    public function test_cache_disabled_bypasses_cache_store()
    {
        Cache::spy();

        $this->createSkillFile('path1/nocache/SKILL.md', 'No Cache Skill');

        $discovery = new SkillDiscovery(
            paths: [$this->tempPath.'/path1'],
            cacheEnabled: false,
        );

        $discovery->discover();
        $discovery->discover();

        Cache::shouldNotHaveReceived('remember');
    }

    public function test_local_mode_does_not_call_remote()
    {
        Http::fake([
            'https://example.com/skills' => Http::response(['skills' => []], 200),
        ]);

        $this->createSkillFile('path1/local-only/SKILL.md', 'Local Only');

        $discovery = new SkillDiscovery(
            paths: [$this->tempPath.'/path1'],
            mode: 'local',
            remoteUrl: 'https://example.com/skills',
            cacheEnabled: false,
        );

        $skills = $discovery->discover();

        $this->assertTrue($skills->has('local-only'));
        Http::assertNothingSent();
    }

    public function test_remote_mode_does_not_scan_local()
    {
        $this->createSkillFile('path1/local-skill/SKILL.md', 'Local Skill');

        Http::fake([
            'https://example.com/skills' => Http::response([
                'skills' => [],
            ], 200),
        ]);

        $discovery = new SkillDiscovery(
            paths: [$this->tempPath.'/path1'],
            mode: 'remote',
            remoteUrl: 'https://example.com/skills',
            cacheEnabled: false,
        );

        $skills = $discovery->discover();

        $this->assertFalse($skills->has('local-skill'));
    }

    public function test_remote_skips_bad_entry_and_continues()
    {
        Http::fake([
            'https://example.com/skills' => Http::response([
                'skills' => [
                    [
                        'description' => 'Missing name field',
                        'instructions' => 'Bad entry',
                    ],
                    [
                        'name' => 'Valid Skill',
                        'description' => 'Valid Description',
                        'instructions' => 'Valid Instructions',
                    ],
                ],
            ], 200),
        ]);

        $discovery = new SkillDiscovery([], 'remote', 'https://example.com/skills', cacheEnabled: false);

        $skills = $discovery->discover();

        $this->assertCount(1, $skills);
        $this->assertTrue($skills->has('valid-skill'));
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
