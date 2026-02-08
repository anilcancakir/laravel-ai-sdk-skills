<?php

declare(strict_types=1);

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Support\Skill;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tools\ListSkills;
use Illuminate\Support\Collection;
use Laravel\Ai\Tools\Request;
use Mockery;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Stubs/Tool.php';

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
        $this->assertStringContainsString('| Name | Description | Triggers | Status |', $result);
        $this->assertStringContainsString('|---|---|---|---|', $result);

        // Check skill 1 (Loaded)
        $this->assertStringContainsString('| git-master | Git operations | git, commit | Loaded |', $result);

        // Check skill 2 (Available)
        $this->assertStringContainsString('| search-docs | Search documentation | search, find | Available |', $result);
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
        $this->assertStringContainsString('| Name | Description | Triggers | Status |', $result);
        $lines = explode("\n", trim($result));
        $this->assertCount(2, $lines, 'Should only contain header and divider');
    }

    public function test_it_defines_correct_metadata(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);
        $tool = new ListSkills($registry);

        $this->assertSame('list_skills', $tool->name());
        $this->assertNotEmpty($tool->description());

        $schemaMock = Mockery::mock(\Illuminate\Contracts\JsonSchema\JsonSchema::class);
        $this->assertIsArray($tool->schema($schemaMock));
        $this->assertEmpty($tool->schema($schemaMock));
    }
}
