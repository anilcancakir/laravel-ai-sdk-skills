<?php

declare(strict_types=1);

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Support\Skill;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillDiscovery;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use AnilcanCakir\LaravelAiSdkSkills\Tools\SkillLoader;
use Laravel\Ai\Tools\Request;
use Mockery;

class SkillLoaderToolTest extends TestCase
{
    public function test_tool_name_is_skill(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);
        $tool = new SkillLoader($registry);

        $this->assertEquals('skill', $tool->name());
    }

    public function test_it_defines_schema(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);
        $tool = new SkillLoader($registry);

        $this->assertNotEmpty($tool->description());
    }

    public function test_it_loads_skill_and_returns_xml_wrapped_instructions(): void
    {
        $discovery = Mockery::mock(SkillDiscovery::class);
        $registry = new SkillRegistry($discovery);

        $skill = new Skill(
            name: 'Test Skill',
            description: 'Description',
            instructions: 'Do this.',
            tools: [],
            triggers: []
        );

        $discovery->shouldReceive('resolve')
            ->with('test-skill')
            ->andReturn($skill);

        $tool = new SkillLoader($registry);

        $result = $tool->handle(new Request(['name' => 'test-skill']));

        $this->assertStringContainsString('<skill name="Test Skill">', (string) $result);
        $this->assertStringContainsString('Do this.', (string) $result);
        $this->assertStringContainsString('</skill>', (string) $result);
        $this->assertTrue($registry->isLoaded('test-skill'));
    }

    public function test_it_returns_error_if_skill_not_found(): void
    {
        $discovery = Mockery::mock(SkillDiscovery::class);
        $registry = new SkillRegistry($discovery);

        $discovery->shouldReceive('resolve')
            ->with('unknown-skill')
            ->andReturn(null);

        $tool = new SkillLoader($registry);

        $result = $tool->handle(new Request(['name' => 'unknown-skill']));

        $this->assertStringContainsString('Skill [unknown-skill] not found', (string) $result);
        $this->assertStringNotContainsString('<skill', (string) $result);
        $this->assertFalse($registry->isLoaded('unknown-skill'));
    }

    public function test_it_includes_reference_files_in_output(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);
        $tempDir = sys_get_temp_dir().'/skill_loader_test_'.uniqid();
        mkdir($tempDir);
        mkdir($tempDir.'/references');

        try {
            file_put_contents($tempDir.'/references/utilities.md', 'utils');
            file_put_contents($tempDir.'/references/theme.md', 'theme');

            $skill = new Skill(
                name: 'Test Skill',
                description: 'Description',
                instructions: 'Do this.',
                tools: [],
                triggers: [],
                basePath: $tempDir
            );

            $registry->shouldReceive('load')->with('test-skill');
            $registry->shouldReceive('get')->with('test-skill')->andReturn($skill);

            $tool = new SkillLoader($registry);
            $result = $tool->handle(new Request(['name' => 'test-skill']));

            $this->assertStringContainsString('<skill_references skill="Test Skill">', (string) $result);
            $this->assertStringContainsString('references/utilities.md', (string) $result);
            $this->assertStringContainsString('references/theme.md', (string) $result);
            $this->assertStringContainsString('skill_read', (string) $result);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function test_it_does_not_include_references_when_none_exist(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);
        $skill = new Skill(
            name: 'Test Skill',
            description: 'Description',
            instructions: 'Do this.',
            tools: [],
            triggers: [],
            basePath: null
        );

        $registry->shouldReceive('load')->with('test-skill');
        $registry->shouldReceive('get')->with('test-skill')->andReturn($skill);

        $tool = new SkillLoader($registry);
        $result = $tool->handle(new Request(['name' => 'test-skill']));

        $this->assertStringNotContainsString('<skill_references', (string) $result);
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = scandir($path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filePath = $path.DIRECTORY_SEPARATOR.$file;
            if (is_dir($filePath)) {
                $this->removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        rmdir($path);
    }
}
