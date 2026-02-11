<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Support\Skill;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillDiscovery;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Mockery;

class SkillRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_it_can_load_a_skill()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);
        $skill = new Skill(
            name: 'Test Skill',
            description: 'A test skill',
            instructions: 'Do the test',
            tools: [],
            triggers: []
        );

        $discovery->shouldReceive('resolve')
            ->with('test-skill')
            ->andReturn($skill);

        $registry = new SkillRegistry($discovery);
        $registry->load('test-skill');

        $this->assertTrue($registry->isLoaded('test-skill'));
    }

    public function test_it_ignores_unknown_skills()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);
        $discovery->shouldReceive('resolve')
            ->with('unknown-skill')
            ->andReturn(null);

        $registry = new SkillRegistry($discovery);
        $registry->load('unknown-skill');

        $this->assertFalse($registry->isLoaded('unknown-skill'));
    }

    public function test_it_resolves_tools_from_loaded_skills()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);

        $toolClass = 'AnilcanCakir\LaravelAiSdkSkills\Tests\Fixtures\TestTool';
        if (! class_exists($toolClass)) {
            eval('namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Fixtures; class TestTool {}');
        }

        $skill = new Skill(
            name: 'Tool Skill',
            description: 'Has tools',
            instructions: 'Use tools',
            tools: [$toolClass],
            triggers: []
        );

        $discovery->shouldReceive('resolve')
            ->with('tool-skill')
            ->andReturn($skill);

        $registry = new SkillRegistry($discovery);
        $registry->load('tool-skill');

        $tools = $registry->tools();

        $this->assertCount(1, $tools);
        $this->assertInstanceOf($toolClass, $tools[0]);
    }

    public function test_it_aggregates_instructions_in_full_mode()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);

        $skill1 = new Skill(
            name: 'Skill A',
            description: 'Desc A',
            instructions: 'Instruction A',
            tools: [],
            triggers: []
        );

        $skill2 = new Skill(
            name: 'Skill B',
            description: 'Desc B',
            instructions: 'Instruction B',
            tools: [],
            triggers: []
        );

        $discovery->shouldReceive('resolve')
            ->with('skill-a')
            ->andReturn($skill1);

        $discovery->shouldReceive('resolve')
            ->with('skill-b')
            ->andReturn($skill2);

        $registry = new SkillRegistry($discovery);
        $registry->load('skill-a');
        $registry->load('skill-b');

        $instructions = $registry->instructions('full');

        $expected = <<<'XML'
<skill name="Skill A">
Instruction A
</skill>
<skill name="Skill B">
Instruction B
</skill>
XML;
        $this->assertEquals(
            trim(str_replace("\r\n", "\n", $expected)),
            trim(str_replace("\r\n", "\n", $instructions))
        );
    }

    public function test_it_returns_only_name_and_description_in_lite_mode()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);

        $skill = new Skill(
            name: 'Wind UI',
            description: 'Utility-first Flutter UI framework',
            instructions: 'Full instructions that should NOT appear in lite mode',
            tools: [],
            triggers: []
        );

        $discovery->shouldReceive('resolve')
            ->with('wind-ui')
            ->andReturn($skill);

        $registry = new SkillRegistry($discovery);
        $registry->load('wind-ui');

        $instructions = $registry->instructions('lite');

        $this->assertStringContainsString('name="Wind UI"', $instructions);
        $this->assertStringContainsString('description="Utility-first Flutter UI framework"', $instructions);
        $this->assertStringNotContainsString('Full instructions that should NOT appear', $instructions);
        $this->assertStringContainsString('/>', $instructions);
    }

    public function test_instructions_defaults_to_lite_mode_from_config()
    {
        config(['skills.discovery_mode' => 'lite']);

        $discovery = Mockery::mock(SkillDiscovery::class);

        $skill = new Skill(
            name: 'Test Skill',
            description: 'A test',
            instructions: 'Should not appear',
            tools: [],
            triggers: []
        );

        $discovery->shouldReceive('resolve')
            ->with('test-skill')
            ->andReturn($skill);

        $registry = new SkillRegistry($discovery);
        $registry->load('test-skill');

        // No mode param — should use config default (lite)
        $instructions = $registry->instructions();

        $this->assertStringNotContainsString('Should not appear', $instructions);
        $this->assertStringContainsString('name="Test Skill"', $instructions);
    }

    public function test_it_handles_missing_tool_classes_gracefully()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);

        $skill = new Skill(
            name: 'Broken Skill',
            description: 'Has missing tool',
            instructions: 'Fail',
            tools: ['NonExistentToolClass'],
            triggers: []
        );

        $discovery->shouldReceive('resolve')
            ->with('broken-skill')
            ->andReturn($skill);

        Log::shouldReceive('warning')->once();

        $registry = new SkillRegistry($discovery);
        $registry->load('broken-skill');

        $tools = $registry->tools();

        $this->assertCount(0, $tools);
    }

    public function test_get_returns_loaded_skill()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);
        $skill = new Skill(
            name: 'Test Skill',
            description: 'A test skill',
            instructions: 'Do the test',
            tools: [],
            triggers: []
        );

        $discovery->shouldReceive('resolve')
            ->with('test-skill')
            ->andReturn($skill);

        $registry = new SkillRegistry($discovery);
        $registry->load('test-skill');

        $result = $registry->get('test-skill');

        $this->assertInstanceOf(Skill::class, $result);
        $this->assertEquals('Test Skill', $result->name);
    }

    public function test_get_returns_null_for_unloaded_skill()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);

        $registry = new SkillRegistry($discovery);

        $this->assertNull($registry->get('nonexistent'));
    }

    public function test_get_loaded_returns_all_loaded_skills()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);

        $skill1 = new Skill(
            name: 'Skill A',
            description: 'Desc A',
            instructions: 'Instruction A',
            tools: [],
            triggers: []
        );

        $skill2 = new Skill(
            name: 'Skill B',
            description: 'Desc B',
            instructions: 'Instruction B',
            tools: [],
            triggers: []
        );

        $discovery->shouldReceive('resolve')
            ->with('skill-a')
            ->andReturn($skill1);

        $discovery->shouldReceive('resolve')
            ->with('skill-b')
            ->andReturn($skill2);

        $registry = new SkillRegistry($discovery);
        $registry->load('skill-a');
        $registry->load('skill-b');

        $loaded = $registry->getLoaded();

        $this->assertCount(2, $loaded);
        $this->assertArrayHasKey('skill-a', $loaded);
        $this->assertArrayHasKey('skill-b', $loaded);
    }

    public function test_loading_same_skill_twice_is_idempotent()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);
        $skill = new Skill(
            name: 'Test Skill',
            description: 'A test skill',
            instructions: 'Do the test',
            tools: [],
            triggers: []
        );

        $discovery->shouldReceive('resolve')
            ->with('test-skill')
            ->andReturn($skill);

        $registry = new SkillRegistry($discovery);
        $registry->load('test-skill');
        $registry->load('test-skill');

        $this->assertCount(1, $registry->getLoaded());
    }

    public function test_tools_aggregates_from_multiple_skills()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);

        $toolClassA = 'AnilcanCakir\LaravelAiSdkSkills\Tests\Fixtures\TestToolA';
        if (! class_exists($toolClassA)) {
            eval('namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Fixtures; class TestToolA {}');
        }

        $toolClassB = 'AnilcanCakir\LaravelAiSdkSkills\Tests\Fixtures\TestToolB';
        if (! class_exists($toolClassB)) {
            eval('namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Fixtures; class TestToolB {}');
        }

        $skill1 = new Skill(
            name: 'Skill A',
            description: 'Has tool A',
            instructions: 'Use tool A',
            tools: [$toolClassA],
            triggers: []
        );

        $skill2 = new Skill(
            name: 'Skill B',
            description: 'Has tool B',
            instructions: 'Use tool B',
            tools: [$toolClassB],
            triggers: []
        );

        $discovery->shouldReceive('resolve')
            ->with('skill-a')
            ->andReturn($skill1);

        $discovery->shouldReceive('resolve')
            ->with('skill-b')
            ->andReturn($skill2);

        $registry = new SkillRegistry($discovery);
        $registry->load('skill-a');
        $registry->load('skill-b');

        $tools = $registry->tools();

        $this->assertCount(2, $tools);
        $this->assertInstanceOf($toolClassA, $tools[0]);
        $this->assertInstanceOf($toolClassB, $tools[1]);
    }
}
