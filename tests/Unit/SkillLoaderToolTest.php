<?php

declare(strict_types=1);

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Support\Skill;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillDiscovery;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use AnilcanCakir\LaravelAiSdkSkills\Tools\SkillLoader;
use Illuminate\Support\Facades\Log;
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

                basePath: $tempDir
            );

            $registry->shouldReceive('load')->with('test-skill')->andReturn($skill);

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

            basePath: null
        );

        $registry->shouldReceive('load')->with('test-skill')->andReturn($skill);

        $tool = new SkillLoader($registry);
        $result = $tool->handle(new Request(['name' => 'test-skill']));

        $this->assertStringNotContainsString('<skill_references', (string) $result);
    }

    public function test_it_refuses_a_directory_that_only_string_prefixes_a_configured_root(): void
    {
        Log::spy();

        $base = sys_get_temp_dir().'/skill_containment_'.uniqid();
        mkdir($base.'/skills', 0755, true);
        mkdir($base.'/skills-evil', 0755, true);

        try {
            file_put_contents($base.'/skills-evil/SKILL.md', <<<'EOT'
---
name: evil-skill
description: Sits right next to a configured root
---

Evil instructions.
EOT
            );

            // The missing root must be skipped, not treated as "/", which would admit everything.
            config(['skills.paths' => [
                'project' => $base.'/skills',
                'missing' => $base.'/never-created',
            ]]);

            $registry = $this->app->make(SkillRegistry::class);
            $tool = new SkillLoader($registry);

            $tool->handle(new Request(['name' => $base.'/skills-evil']));

            $this->assertFalse($registry->isLoaded('evil-skill'));
            Log::shouldHaveReceived('warning')->atLeast()->once();
        } finally {
            $this->removeDirectory($base);
        }
    }

    public function test_it_renders_a_skill_loaded_by_path_inside_a_configured_root(): void
    {
        $root = sys_get_temp_dir().'/skill_containment_'.uniqid();
        mkdir($root.'/nested-skill', 0755, true);

        try {
            file_put_contents($root.'/nested-skill/SKILL.md', <<<'EOT'
---
name: nested-skill
description: Lives inside a configured root
---

Nested instructions.
EOT
            );

            // A trailing separator on the configured root must not break containment.
            config(['skills.paths' => ['project' => $root.DIRECTORY_SEPARATOR]]);

            $registry = $this->app->make(SkillRegistry::class);
            $tool = new SkillLoader($registry);

            $result = (string) $tool->handle(new Request(['name' => $root.'/nested-skill']));

            $this->assertStringContainsString('<skill name="nested-skill">', $result);
            $this->assertStringContainsString('Nested instructions.', $result);
            $this->assertTrue($registry->isLoaded('nested-skill'));
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function test_it_loads_a_skill_directory_that_is_itself_a_configured_root(): void
    {
        $root = sys_get_temp_dir().'/skill_root_'.uniqid();
        mkdir($root, 0755, true);

        try {
            file_put_contents($root.'/SKILL.md', <<<'EOT'
---
name: root-skill
description: The configured root is the skill directory itself
---

Root instructions.
EOT
            );

            config(['skills.paths' => ['project' => $root]]);

            $registry = $this->app->make(SkillRegistry::class);
            $tool = new SkillLoader($registry);

            $tool->handle(new Request(['name' => $root]));

            $this->assertTrue($registry->isLoaded('root-skill'));
        } finally {
            $this->removeDirectory($root);
        }
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
