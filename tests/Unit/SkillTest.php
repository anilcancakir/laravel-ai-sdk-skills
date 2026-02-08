<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Support\Skill;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;

class SkillTest extends TestCase
{
    public function test_it_can_be_instantiated_as_a_dto()
    {
        $skill = new Skill(
            name: 'Git Master',
            description: 'Expert in git operations',
            instructions: 'Do git things',
            tools: ['git_status', 'git_commit'],
            triggers: ['commit', 'push']
        );

        $this->assertEquals('Git Master', $skill->name);
        $this->assertEquals('Expert in git operations', $skill->description);
        $this->assertEquals('Do git things', $skill->instructions);
        $this->assertEquals(['git_status', 'git_commit'], $skill->tools);
        $this->assertEquals(['commit', 'push'], $skill->triggers);
    }

    public function test_it_generates_slug_from_name()
    {
        $skill = new Skill(
            name: 'Git Master',
            description: 'desc',
            instructions: 'inst',
            tools: [],
            triggers: []
        );

        $this->assertEquals('git-master', $skill->slug());
    }

    public function test_it_checks_if_it_has_tools()
    {
        $skillWithTools = new Skill(
            name: 'Test',
            description: 'desc',
            instructions: 'inst',
            tools: ['tool1'],
            triggers: []
        );

        $skillWithoutTools = new Skill(
            name: 'Test',
            description: 'desc',
            instructions: 'inst',
            tools: [],
            triggers: []
        );

        $this->assertTrue($skillWithTools->hasTools());
        $this->assertFalse($skillWithoutTools->hasTools());
    }

    public function test_it_matches_trigger()
    {
        $skill = new Skill(
            name: 'Test',
            description: 'desc',
            instructions: 'inst',
            tools: [],
            triggers: ['commit', 'rebase']
        );

        $this->assertTrue($skill->matchesTrigger('Please commit this change'));
        $this->assertTrue($skill->matchesTrigger('rebase my branch'));
        $this->assertFalse($skill->matchesTrigger('just a normal message'));
    }

    public function test_it_matches_trigger_case_insensitively()
    {
        $skill = new Skill(
            name: 'Test',
            description: 'desc',
            instructions: 'inst',
            tools: [],
            triggers: ['Git']
        );

        $this->assertTrue($skill->matchesTrigger('I love git'));
    }

    public function test_it_handles_empty_triggers_gracefully()
    {
        $skill = new Skill(
            name: 'Test',
            description: 'desc',
            instructions: 'inst',
            tools: [],
            triggers: []
        );

        $this->assertFalse($skill->matchesTrigger('any message'));
    }
}
