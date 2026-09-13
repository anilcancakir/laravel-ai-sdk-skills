<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use AnilcanCakir\LaravelAiSdkSkills\Tools\ListSkills;
use AnilcanCakir\LaravelAiSdkSkills\Tools\SkillLoader;
use AnilcanCakir\LaravelAiSdkSkills\Tools\SkillReferenceReader;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use ReflectionMethod;
use Stringable;

/**
 * Guards the public visibility of each tool's name() method.
 *
 * Laravel\Ai\Tools\ToolNameResolver::resolve() falls back to class_basename()
 * when name() is not publicly callable, so a reduction in visibility would
 * silently rename a tool for every LLM with nothing failing loudly. This
 * test locks both the name VALUE and the public visibility that keeps
 * ToolNameResolver from ever reaching that fallback.
 */
class ToolNameResolutionTest extends TestCase
{
    public function test_list_skills_name_is_public_and_reachable(): void
    {
        $this->assertTrue((new ReflectionMethod(ListSkills::class, 'name'))->isPublic());
        $this->assertTrue(is_callable([$this->app->make(ListSkills::class), 'name']));
        $this->assertEquals('list_skills', $this->app->make(ListSkills::class)->name());
    }

    public function test_skill_loader_name_is_public_and_reachable(): void
    {
        $this->assertTrue((new ReflectionMethod(SkillLoader::class, 'name'))->isPublic());
        $this->assertTrue(is_callable([$this->app->make(SkillLoader::class), 'name']));
        $this->assertEquals('skill', $this->app->make(SkillLoader::class)->name());
    }

    public function test_skill_reference_reader_name_is_public_and_reachable(): void
    {
        $this->assertTrue((new ReflectionMethod(SkillReferenceReader::class, 'name'))->isPublic());
        $this->assertTrue(is_callable([$this->app->make(SkillReferenceReader::class), 'name']));
        $this->assertEquals('skill_read', $this->app->make(SkillReferenceReader::class)->name());
    }

    /**
     * Proves the reachability check above actually distinguishes visibility.
     *
     * A protected name() satisfies the Tool contract (name() is not part of
     * it) but is_callable() returns false, which is exactly the condition
     * ToolNameResolver uses to decide whether to fall back to class_basename().
     */
    public function test_protected_name_is_not_reachable_via_is_callable(): void
    {
        $fixture = new class implements Tool
        {
            protected function name(): string
            {
                return 'protected_name_fixture';
            }

            public function description(): Stringable|string
            {
                return 'Fixture tool used only to prove the visibility check works.';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function handle(Request $request): Stringable|string
            {
                return '';
            }
        };

        $this->assertFalse(is_callable([$fixture, 'name']));
    }
}
