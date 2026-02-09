<?php

declare(strict_types=1);

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Support\Skill;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use AnilcanCakir\LaravelAiSdkSkills\Tools\ListSkills;
use Illuminate\Support\Collection;
use Laravel\Ai\Tools\Request;
use Mockery;

class ListSkillsToolTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_lists_available_skills_formatted_as_markdown_table(): void
    {
        // Arrange
        $registry = Mockery::mock(SkillRegistry::class);

        $skill1 = new Skill(
            name: 'git-master',
            description: 'Git operations',
            instructions: 'Use git...',
            tools: [],
            triggers: ['git', 'commit']
        );

        $skill2 = new Skill(
            name: 'search-docs',
            description: 'Search documentation',
            instructions: 'Search docs...',
            tools: [],
            triggers: ['search', 'find']
        );

        $registry->shouldReceive('available')
            ->once()
            ->andReturn(new Collection([
                'git-master' => $skill1,
                'search-docs' => $skill2,
            ]));

        $registry->shouldReceive('getLoaded')
            ->once()
            ->andReturn([
                'git-master' => $skill1,
            ]);

        $tool = new ListSkills($registry);

        // Act
        $result = $tool->handle(new Request([]));

        // Assert
        $this->assertStringContainsString('| Name | Description | Triggers | Source | Status |', (string) $result);
        $this->assertStringContainsString('|---|---|---|---|---|', (string) $result);

        // Check skill 1 (Loaded)
        $this->assertStringContainsString('| git-master | Git operations | git, commit | Local | Loaded |', (string) $result);

        // Check skill 2 (Available)
        $this->assertStringContainsString('| search-docs | Search documentation | search, find | Local | Available |', (string) $result);
    }

    public function test_it_handles_no_skills_available(): void
    {
        // Arrange
        $registry = Mockery::mock(SkillRegistry::class);

        $registry->shouldReceive('available')
            ->once()
            ->andReturn(new Collection([]));

        $registry->shouldReceive('getLoaded')
            ->once()
            ->andReturn([]);

        $tool = new ListSkills($registry);

        // Act
        $result = $tool->handle(new Request([]));

        // Assert
        $this->assertStringContainsString('No skills found', (string) $result);
    }

    public function test_it_defines_correct_metadata(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);

        $registry->shouldReceive('available')
            ->once()
            ->andReturn(new Collection([]));

        $tool = new ListSkills($registry);

        $this->assertEquals('list_skills', $tool->name());
        $this->assertNotEmpty($tool->description());
    }

    public function test_description_contains_available_skills_xml(): void
    {
        // Arrange
        $registry = Mockery::mock(SkillRegistry::class);

        $skill1 = new Skill(
            name: 'git-master',
            description: 'Git operations and history',
            instructions: 'Use git...',
            tools: [],
            triggers: ['git', 'commit']
        );

        $skill2 = new Skill(
            name: 'search-docs',
            description: 'Search documentation',
            instructions: 'Search docs...',
            tools: [],
            triggers: ['search', 'find']
        );

        $registry->shouldReceive('available')
            ->once()
            ->andReturn(new Collection([
                'git-master' => $skill1,
                'search-docs' => $skill2,
            ]));

        $tool = new ListSkills($registry);

        // Act
        $description = (string) $tool->description();

        // Assert
        $this->assertStringContainsString('<available_skills>', $description);
        $this->assertStringContainsString('</available_skills>', $description);
        $this->assertStringContainsString('<skill>', $description);
        $this->assertStringContainsString('<name>git-master</name>', $description);
        $this->assertStringContainsString('<description>Git operations and history</description>', $description);
        $this->assertStringContainsString('<name>search-docs</name>', $description);
        $this->assertStringContainsString('<description>Search documentation</description>', $description);
    }

    public function test_description_with_empty_skills_returns_valid_xml(): void
    {
        // Arrange
        $registry = Mockery::mock(SkillRegistry::class);

        $registry->shouldReceive('available')
            ->once()
            ->andReturn(new Collection([]));

        $tool = new ListSkills($registry);

        // Act
        $description = (string) $tool->description();

        // Assert
        $this->assertStringContainsString('<available_skills>', $description);
        $this->assertStringContainsString('</available_skills>', $description);
        // Should still have the base description text
        $this->assertStringContainsString('List all available skills', $description);
    }

    public function test_description_respects_max_skills_limit(): void
    {
        // Arrange
        $registry = Mockery::mock(SkillRegistry::class);

        // Create 60 skills (more than default limit of 50)
        $skills = [];
        for ($i = 1; $i <= 60; $i++) {
            $skills["skill-{$i}"] = new Skill(
                name: "skill-{$i}",
                description: "Description {$i}",
                instructions: 'Instructions...',
                tools: [],
                triggers: []
            );
        }

        $registry->shouldReceive('available')
            ->once()
            ->andReturn(new Collection($skills));

        $tool = new ListSkills($registry);

        // Act
        $description = (string) $tool->description();

        // Assert
        $this->assertStringContainsString('<available_skills>', $description);
        // Count skill tags - should be limited to 50
        $skillCount = substr_count($description, '<skill>');
        $this->assertLessThanOrEqual(50, $skillCount);
    }

    public function test_description_includes_instructions_in_full_mode(): void
    {
        // Arrange
        $registry = Mockery::mock(SkillRegistry::class);

        $skill = new Skill(
            name: 'git-master',
            description: 'Git operations',
            instructions: 'Detailed git instructions...',
            tools: [],
            triggers: []
        );

        $registry->shouldReceive('available')
            ->once()
            ->andReturn(new Collection(['git-master' => $skill]));

        // Initialize with 'full' mode
        $tool = new ListSkills($registry, 50, 'full');

        // Act
        $description = (string) $tool->description();

        // Assert
        $this->assertStringContainsString('<name>git-master</name>', $description);
        $this->assertStringContainsString('<description>Git operations</description>', $description);
        $this->assertStringContainsString('<instructions>Detailed git instructions...</instructions>', $description);
    }

    public function test_description_excludes_instructions_in_lite_mode(): void
    {
        // Arrange
        $registry = Mockery::mock(SkillRegistry::class);

        $skill = new Skill(
            name: 'git-master',
            description: 'Git operations',
            instructions: 'Detailed git instructions...',
            tools: [],
            triggers: []
        );

        $registry->shouldReceive('available')
            ->once()
            ->andReturn(new Collection(['git-master' => $skill]));

        // Initialize with 'lite' mode
        $tool = new ListSkills($registry, 50, 'lite');

        // Act
        $description = (string) $tool->description();

        // Assert
        $this->assertStringContainsString('<name>git-master</name>', $description);
        $this->assertStringContainsString('<description>Git operations</description>', $description);
        $this->assertStringNotContainsString('<instructions>', $description);
    }
}
