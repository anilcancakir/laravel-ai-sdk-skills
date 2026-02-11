<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Support\Skill;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use AnilcanCakir\LaravelAiSdkSkills\Tools\SkillReferenceReader;
use Laravel\Ai\Tools\Request;
use Mockery;

class SkillReferenceReaderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_tool_name_is_skill_read(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);
        $tool = new SkillReferenceReader($registry);

        $this->assertEquals('skill_read', $tool->name());
    }

    public function test_it_defines_schema(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);
        $tool = new SkillReferenceReader($registry);

        $this->assertNotEmpty($tool->description());
        // We can't easily test schema() output without a real JsonSchema implementation,
        // but we can verify it exists and is callable.
        $this->assertTrue(method_exists($tool, 'schema'));
    }

    public function test_it_reads_valid_file_from_skill(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);

        // Create a temporary directory and file for testing
        $tempDir = sys_get_temp_dir().'/skill_test_'.uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir.'/test.md', '# Content');

        $skill = new Skill(
            name: 'Test Skill',
            description: 'Desc',
            instructions: 'Inst',
            tools: [],

            basePath: $tempDir
        );

        $registry->shouldReceive('get')
            ->with('test-skill')
            ->andReturn($skill);

        $tool = new SkillReferenceReader($registry);

        $result = $tool->handle(new Request([
            'skill' => 'test-skill',
            'file' => 'test.md',
        ]));

        $this->assertStringContainsString('<skill_reference skill="Test Skill" file="test.md">', (string) $result);
        $this->assertStringContainsString('# Content', (string) $result);
        $this->assertStringContainsString('</skill_reference>', (string) $result);

        // Cleanup
        unlink($tempDir.'/test.md');
        rmdir($tempDir);
    }

    public function test_it_blocks_directory_traversal(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);

        $tempDir = sys_get_temp_dir().'/skill_test_'.uniqid();
        mkdir($tempDir);

        // Create a secret file outside
        $secretFile = $tempDir.'/../secret.txt';
        file_put_contents($secretFile, 'SECRET');

        $skill = new Skill(
            name: 'Test Skill',
            description: 'Desc',
            instructions: 'Inst',
            tools: [],

            basePath: $tempDir
        );

        $registry->shouldReceive('get')
            ->with('test-skill')
            ->andReturn($skill);

        $tool = new SkillReferenceReader($registry);

        // Attempt traversal
        $result = $tool->handle(new Request([
            'skill' => 'test-skill',
            'file' => '../secret.txt',
        ]));

        $this->assertStringContainsString('Error: Access denied', (string) $result);
        $this->assertStringNotContainsString('SECRET', (string) $result);

        // Cleanup
        unlink($secretFile);
        rmdir($tempDir);
    }

    public function test_it_returns_error_if_skill_not_loaded(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);

        $registry->shouldReceive('get')
            ->with('unknown-skill')
            ->andReturn(null);

        $tool = new SkillReferenceReader($registry);

        $result = $tool->handle(new Request([
            'skill' => 'unknown-skill',
            'file' => 'test.md',
        ]));

        $this->assertStringContainsString('Skill [unknown-skill] is not loaded', (string) $result);
    }

    public function test_it_returns_error_if_skill_has_no_basepath(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);

        $skill = new Skill(
            name: 'Remote Skill',
            description: 'Desc',
            instructions: 'Inst',
            tools: [],

            basePath: null // Remote skills have no local path
        );

        $registry->shouldReceive('get')
            ->with('remote-skill')
            ->andReturn($skill);

        $tool = new SkillReferenceReader($registry);

        $result = $tool->handle(new Request([
            'skill' => 'remote-skill',
            'file' => 'test.md',
        ]));

        $this->assertStringContainsString('has no base path', (string) $result);
    }

    public function test_it_returns_error_if_file_not_found(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);

        $tempDir = sys_get_temp_dir().'/skill_test_'.uniqid();
        mkdir($tempDir);

        $skill = new Skill(
            name: 'Test Skill',
            description: 'Desc',
            instructions: 'Inst',
            tools: [],

            basePath: $tempDir
        );

        $registry->shouldReceive('get')
            ->with('test-skill')
            ->andReturn($skill);

        $tool = new SkillReferenceReader($registry);

        $result = $tool->handle(new Request([
            'skill' => 'test-skill',
            'file' => 'nonexistent.md',
        ]));

        $this->assertStringContainsString('File "nonexistent.md" not found', (string) $result);

        rmdir($tempDir);
    }

    public function test_it_reads_files_from_subdirectories(): void
    {
        $registry = Mockery::mock(SkillRegistry::class);

        // Create a temporary directory and subdirectory file for testing
        $tempDir = sys_get_temp_dir().'/skill_test_'.uniqid();
        mkdir($tempDir);
        mkdir($tempDir.'/docs');
        file_put_contents($tempDir.'/docs/guide.md', '# Guide');

        $skill = new Skill(
            name: 'Test Skill',
            description: 'Desc',
            instructions: 'Inst',
            tools: [],

            basePath: $tempDir
        );

        $registry->shouldReceive('get')
            ->with('test-skill')
            ->andReturn($skill);

        $tool = new SkillReferenceReader($registry);

        $result = $tool->handle(new Request([
            'skill' => 'test-skill',
            'file' => 'docs/guide.md',
        ]));

        $this->assertStringContainsString('<skill_reference skill="Test Skill" file="docs/guide.md">', (string) $result);
        $this->assertStringContainsString('# Guide', (string) $result);
        $this->assertStringContainsString('</skill_reference>', (string) $result);

        // Cleanup
        unlink($tempDir.'/docs/guide.md');
        rmdir($tempDir.'/docs');
        rmdir($tempDir);
    }
}
