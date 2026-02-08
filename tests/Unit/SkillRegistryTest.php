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

    public function test_it_aggregates_instructions()
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

        $instructions = $registry->instructions();

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
}
