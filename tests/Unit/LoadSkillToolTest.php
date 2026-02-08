<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Support\Skill;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillDiscovery;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use AnilcanCakir\LaravelAiSdkSkills\Tools\LoadSkill;
use Mockery;

class LoadSkillToolTest extends TestCase
{
    public function test_it_defines_schema()
    {
        $registry = Mockery::mock(SkillRegistry::class);
        $tool = new LoadSkill($registry);

        $schema = $tool->schema();

        $this->assertEquals('load_skill', $tool->name());
        $this->assertNotEmpty($tool->description());
    }

    public function test_it_loads_skill_and_returns_instructions()
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

        $tool = new LoadSkill($registry);

        $result = $tool->handle(['name' => 'test-skill']);

        $this->assertStringContainsString('Skill [Test Skill] loaded', (string) $result);
        $this->assertStringContainsString('Do this.', (string) $result);
        $this->assertTrue($registry->isLoaded('test-skill'));
    }

    public function test_it_returns_error_if_skill_not_found()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);
        $registry = new SkillRegistry($discovery);

        $discovery->shouldReceive('resolve')
            ->with('unknown-skill')
            ->andReturn(null);

        $tool = new LoadSkill($registry);

        $result = $tool->handle(['name' => 'unknown-skill']);

        $this->assertStringContainsString('Skill [unknown-skill] not found', (string) $result);
        $this->assertFalse($registry->isLoaded('unknown-skill'));
    }
}
