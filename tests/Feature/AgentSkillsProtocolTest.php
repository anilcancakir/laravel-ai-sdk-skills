<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Feature;

use AnilcanCakir\LaravelAiSdkSkills\Support\Skill;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillDiscovery;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use AnilcanCakir\LaravelAiSdkSkills\Tools\ListSkills;
use AnilcanCakir\LaravelAiSdkSkills\Tools\SkillLoader;
use Laravel\Ai\Tools\Request;
use Mockery;

class AgentSkillsProtocolTest extends TestCase
{
    public function test_list_skills_description_contains_available_skills_xml(): void
    {
        $discovery = Mockery::mock(SkillDiscovery::class);
        $discovery->shouldReceive('discover')->andReturn(collect([
            'test-skill' => new Skill(
                name: 'test-skill',
                description: 'A test skill description',
                instructions: 'Do something',
                tools: [],

            ),
        ]));

        $registry = new SkillRegistry($discovery);
        $tool = new ListSkills($registry);

        $description = (string) $tool->description();

        $this->assertStringContainsString('<available_skills>', $description);
        $this->assertStringContainsString('<skill>', $description);
        $this->assertStringContainsString('<name>test-skill</name>', $description);
        $this->assertStringContainsString('<description>A test skill description</description>', $description);
        $this->assertStringContainsString('</available_skills>', $description);
    }

    public function test_skill_loader_has_correct_tool_name(): void
    {
        $registry = $this->app->make(SkillRegistry::class);
        $tool = new SkillLoader($registry);

        $this->assertEquals('skill', $tool->name());
    }

    public function test_skill_loader_returns_xml_wrapped_instructions(): void
    {
        $discovery = Mockery::mock(SkillDiscovery::class);
        $discovery->shouldReceive('resolve')->with('test-skill')->andReturn(new Skill(
            name: 'test-skill',
            description: 'Test description',
            instructions: 'Execute test logic.',
            tools: []
        ));

        $registry = new SkillRegistry($discovery);
        $tool = new SkillLoader($registry);

        $response = (string) $tool->handle(new Request(['name' => 'test-skill']));

        $this->assertStringContainsString('<skill name="test-skill">', $response);
        $this->assertStringContainsString('Execute test logic.', $response);
        $this->assertStringContainsString('</skill>', $response);
    }

    public function test_all_tools_have_correct_names(): void
    {
        $registry = $this->app->make(SkillRegistry::class);

        $listSkills = new ListSkills($registry);
        $this->assertEquals('list_skills', $listSkills->name());

        $skillLoader = new SkillLoader($registry);
        $this->assertEquals('skill', $skillLoader->name());

        // Verify SearchDocs tool name if it exists in the app namespace as per FullWorkflowTest pattern
        if (class_exists('App\Ai\Tools\SearchDocs')) {
            $searchDocs = new \App\Ai\Tools\SearchDocs;
            $this->assertEquals('search_docs', $searchDocs->name());
        }
    }
}
