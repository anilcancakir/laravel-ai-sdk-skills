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
}
