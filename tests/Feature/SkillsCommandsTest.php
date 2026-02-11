<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Feature;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillDiscovery;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class SkillsCommandsTest extends TestCase
{
    protected string $testSkillPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Define a temp path for skills
        $this->testSkillPath = __DIR__.'/../fixtures/temp_skills';

        // Ensure it's clean
        $this->cleanupTestDir();
        File::makeDirectory($this->testSkillPath, 0755, true);

        // Point config to this temp path
        Config::set('skills.paths', [$this->testSkillPath]);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestDir();
        parent::tearDown();
    }

    protected function cleanupTestDir(): void
    {
        if (File::exists($this->testSkillPath)) {
            File::deleteDirectory($this->testSkillPath);
        }
    }

    public function test_skills_list_command_shows_empty_message_when_no_skills()
    {
        // Clear instance to ensure fresh discovery from empty dir
        $this->app->forgetInstance(SkillDiscovery::class);

        $this->artisan('skills:list')
            ->expectsOutput('No skills found.')
            ->assertExitCode(0);
    }

    public function test_skills_list_command_shows_skills_table()
    {
        // Create a dummy skill
        $skillDir = $this->testSkillPath.'/TestSkill';
        File::makeDirectory($skillDir, 0755, true);
        File::put($skillDir.'/SKILL.md', <<<'EOT'
---
name: Test Skill
description: A test skill for unit testing
tools: [tool_a, tool_b]
---
# Instructions
Do something.
EOT
        );

        // Re-bind discovery to pick up new files
        $this->app->forgetInstance(SkillDiscovery::class);

        $this->artisan('skills:list')
            ->expectsTable(
                ['Name', 'Description', 'Tools'],
                [
                    ['Test Skill', 'A test skill for unit testing', 'tool_a, tool_b'],
                ]
            )
            ->assertExitCode(0);
    }

    public function test_skills_make_command_creates_skill()
    {
        $this->artisan('skills:make', ['name' => 'NewFeature'])
            ->expectsOutput('Skill [NewFeature] created successfully.')
            ->assertExitCode(0);

        $path = $this->testSkillPath.'/NewFeature/SKILL.md';
        $this->assertFileExists($path);

        $content = File::get($path);
        $this->assertStringContainsString('name: NewFeature', $content);
        $this->assertStringContainsString('# NewFeature', $content);
    }

    public function test_skills_make_command_fails_if_skill_exists()
    {
        $this->artisan('skills:make', ['name' => 'DuplicateSkill']);

        $this->artisan('skills:make', ['name' => 'DuplicateSkill'])
            ->expectsOutput('Skill [DuplicateSkill] already exists.')
            ->assertExitCode(1);
    }

    public function test_skills_make_command_uses_custom_description()
    {
        $this->artisan('skills:make', [
            'name' => 'DescribedSkill',
            '--description' => 'Custom description',
        ]);

        $content = File::get($this->testSkillPath.'/DescribedSkill/SKILL.md');
        $this->assertStringContainsString('description: Custom description', $content);
    }

    public function test_skills_clear_command_clears_cache()
    {
        $this->artisan('skills:clear')
            ->expectsOutput('Skills cache cleared successfully.')
            ->assertExitCode(0);
    }
}
