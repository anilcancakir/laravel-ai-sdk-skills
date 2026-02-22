<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Enums\SkillInclusionMode;
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

        );

        $skill2 = new Skill(
            name: 'Skill B',
            description: 'Desc B',
            instructions: 'Instruction B',
            tools: [],

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

    public function test_it_uses_per_skill_modes_with_config_fallback()
    {
        config(['skills.discovery_mode' => 'lite']);

        $discovery = Mockery::mock(SkillDiscovery::class);

        $fullSkill = new Skill(
            name: 'skill-full',
            description: 'Full mode skill',
            instructions: 'Full instructions visible.',
            tools: [],
        );

        $defaultSkill = new Skill(
            name: 'skill-default',
            description: 'Default mode skill',
            instructions: 'Default instructions hidden in lite.',
            tools: [],
        );

        $discovery->shouldReceive('resolve')
            ->with('skill-full')
            ->andReturn($fullSkill);

        $discovery->shouldReceive('resolve')
            ->with('skill-default')
            ->andReturn($defaultSkill);

        $registry = new SkillRegistry($discovery);
        $registry->load('skill-full', SkillInclusionMode::Full);
        $registry->load('skill-default');

        $instructions = $registry->instructions();

        $this->assertStringContainsString('<skill name="skill-full">', $instructions);
        $this->assertStringContainsString('Full instructions visible.', $instructions);
        $this->assertStringContainsString('<skill name="skill-default" description="Default mode skill" />', $instructions);
        $this->assertStringNotContainsString('Default instructions hidden in lite.', $instructions);
    }

    public function test_global_override_forces_all_skills_mode()
    {
        config(['skills.discovery_mode' => 'lite']);

        $discovery = Mockery::mock(SkillDiscovery::class);

        $skillA = new Skill(
            name: 'skill-a',
            description: 'Skill A',
            instructions: 'Instruction A',
            tools: [],
        );

        $skillB = new Skill(
            name: 'skill-b',
            description: 'Skill B',
            instructions: 'Instruction B',
            tools: [],
        );

        $discovery->shouldReceive('resolve')
            ->with('skill-a')
            ->andReturn($skillA);

        $discovery->shouldReceive('resolve')
            ->with('skill-b')
            ->andReturn($skillB);

        $registry = new SkillRegistry($discovery);
        $registry->load('skill-a', SkillInclusionMode::Full);
        $registry->load('skill-b', SkillInclusionMode::Full);

        $instructions = $registry->instructions('lite');

        $this->assertStringNotContainsString('Instruction A', $instructions);
        $this->assertStringNotContainsString('Instruction B', $instructions);
        $this->assertStringContainsString('description="Skill A"', $instructions);
        $this->assertStringContainsString('description="Skill B"', $instructions);
    }

    public function test_invalid_per_skill_mode_logs_warning_and_falls_back_to_config()
    {
        config(['skills.discovery_mode' => 'lite']);

        $discovery = Mockery::mock(SkillDiscovery::class);
        $skill = new Skill(
            name: 'invalid-mode-skill',
            description: 'Skill with invalid mode',
            instructions: 'Should not appear in lite.',
            tools: [],
        );

        $discovery->shouldReceive('resolve')
            ->with('invalid-mode-skill')
            ->andReturn($skill);

        Log::shouldReceive('warning')->once();

        $registry = new SkillRegistry($discovery);
        $registry->load('invalid-mode-skill', 'fast');

        $instructions = $registry->instructions();

        $this->assertStringContainsString('description="Skill with invalid mode"', $instructions);
        $this->assertStringNotContainsString('Should not appear in lite.', $instructions);
    }

    public function test_invalid_global_override_logs_warning_and_falls_back_to_config()
    {
        config(['skills.discovery_mode' => 'full']);

        $discovery = Mockery::mock(SkillDiscovery::class);
        $skill = new Skill(
            name: 'fallback-skill',
            description: 'Fallback mode skill',
            instructions: 'Visible in full mode.',
            tools: [],
        );

        $discovery->shouldReceive('resolve')
            ->with('fallback-skill')
            ->andReturn($skill);

        Log::shouldReceive('warning')->once();

        $registry = new SkillRegistry($discovery);
        $registry->load('fallback-skill');

        $instructions = $registry->instructions('invalid');

        $this->assertStringContainsString('<skill name="fallback-skill">', $instructions);
        $this->assertStringContainsString('Visible in full mode.', $instructions);
    }

    public function test_it_handles_missing_tool_classes_gracefully()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);

        $skill = new Skill(
            name: 'Broken Skill',
            description: 'Has missing tool',
            instructions: 'Fail',
            tools: ['NonExistentToolClass'],

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

        );

        $skill2 = new Skill(
            name: 'Skill B',
            description: 'Desc B',
            instructions: 'Instruction B',
            tools: [],

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

    public function test_reload_without_mode_preserves_existing_per_skill_mode()
    {
        config(['skills.discovery_mode' => 'lite']);

        $discovery = Mockery::mock(SkillDiscovery::class);
        $skill = new Skill(
            name: 'persistent-skill',
            description: 'Skill with persistent mode',
            instructions: 'Should remain full after re-load.',
            tools: [],
        );

        $discovery->shouldReceive('resolve')
            ->with('persistent-skill')
            ->andReturn($skill);

        $registry = new SkillRegistry($discovery);
        $registry->load('persistent-skill', SkillInclusionMode::Full);

        // Re-load without mode (simulates SkillLoader tool re-scan)
        $registry->load('persistent-skill');

        $instructions = $registry->instructions();

        $this->assertStringContainsString('<skill name="persistent-skill">', $instructions);
        $this->assertStringContainsString('Should remain full after re-load.', $instructions);
    }

    public function test_loading_same_skill_twice_is_idempotent()
    {
        $discovery = Mockery::mock(SkillDiscovery::class);
        $skill = new Skill(
            name: 'Test Skill',
            description: 'A test skill',
            instructions: 'Do the test',
            tools: [],

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

        );

        $skill2 = new Skill(
            name: 'Skill B',
            description: 'Has tool B',
            instructions: 'Use tool B',
            tools: [$toolClassB],

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
