<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Feature;

use AnilcanCakir\LaravelAiSdkSkills\Enums\SkillInclusionMode;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use AnilcanCakir\LaravelAiSdkSkills\Traits\Skillable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SkillableTraitTest extends TestCase
{
    public function test_skills_are_loaded_on_first_skill_tools_call()
    {
        // Arrange: Create a temporary skill in a discovered path
        $skillPath = __DIR__.'/../fixtures/skills/test-skill';
        if (! File::exists($skillPath)) {
            File::makeDirectory($skillPath, 0755, true);
        }
        File::put($skillPath.'/SKILL.md', <<<'EOT'
---
name: test-skill
description: A test skill
---
# Instructions
Do the test thing.
EOT
        );

        // Act: Instantiate a class using Skillable
        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['test-skill'];
            }
        };

        // Skills are NOT loaded yet (lazy boot)
        $this->assertFalse(resolve(SkillRegistry::class)->isLoaded('test-skill'));

        // Trigger lazy boot by calling skillTools()
        $agent->skillTools();

        // Assert: Now skill is loaded
        $this->assertTrue(resolve(SkillRegistry::class)->isLoaded('test-skill'));

        // Cleanup
        File::deleteDirectory($skillPath);
    }

    public function test_load_by_path()
    {
        config(['skills.discovery_mode' => 'full']);

        // Arrange: Create a temp skill in a random location
        $tempPath = storage_path('temp-skills/path-skill');
        if (! File::exists($tempPath)) {
            File::makeDirectory($tempPath, 0755, true);
        }
        File::put($tempPath.'/SKILL.md', <<<'EOT'
---
name: path-skill
description: A path loaded skill
---
# Path Instructions
Loaded via path.
EOT
        );

        // Act
        $agent = new class($tempPath)
        {
            use Skillable;

            protected $path;

            public function __construct($path)
            {
                $this->path = $path;
            }

            public function skills(): iterable
            {
                return [$this->path];
            }
        };

        // Trigger lazy boot
        $agent->skillTools();

        // Assert
        $this->assertTrue(resolve(SkillRegistry::class)->isLoaded('path-skill'));
        $this->assertStringContainsString('Loaded via path.', $agent->skillInstructions());

        // Cleanup
        File::deleteDirectory(storage_path('temp-skills'));
    }

    public function test_skill_tools_retrieval()
    {
        // Arrange
        $agent = new class
        {
            use Skillable;
        };

        // Act
        $tools = $agent->skillTools();

        // Assert
        // By default, meta tools (ListSkills, LoadSkill) might be present or not depending on config/boot
        // But mainly we check it returns an array
        $this->assertIsArray($tools);
    }

    public function test_skill_instructions_mode_override()
    {
        // Arrange: Config is 'lite' by default
        config(['skills.discovery_mode' => 'lite']);

        $skillPath = __DIR__.'/../fixtures/skills/test-skill';
        if (! File::exists($skillPath)) {
            File::makeDirectory($skillPath, 0755, true);
        }
        File::put($skillPath.'/SKILL.md', <<<'EOT'
---
name: test-skill
description: A test skill
---
# Instructions
Do the test thing.
EOT
        );

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['test-skill'];
            }
        };

        // Act & Assert: Default (lite) - should NOT contain full instructions
        $lite = $agent->skillInstructions();
        $this->assertStringContainsString('test-skill', $lite);
        $this->assertStringNotContainsString('Do the test thing.', $lite);

        // Act & Assert: Override to 'full' - should contain full instructions
        $full = $agent->skillInstructions('full');
        $this->assertStringContainsString('Do the test thing.', $full);
        $this->assertStringContainsString('<skill name="test-skill">', $full);

        // Act & Assert: Override to 'lite' explicitly
        $liteExplicit = $agent->skillInstructions('lite');
        $this->assertStringNotContainsString('Do the test thing.', $liteExplicit);
        $this->assertStringContainsString('description="A test skill"', $liteExplicit);

        // Cleanup
        File::deleteDirectory($skillPath);
    }

    public function test_skills_are_not_loaded_when_disabled()
    {
        // Arrange
        config(['skills.enabled' => false]);

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['test-skill'];
            }
        };

        // Act
        $tools = $agent->skillTools();
        $instructions = $agent->skillInstructions();

        // Assert
        $this->assertEmpty($tools);
        $this->assertEmpty($instructions);
        $this->assertFalse(resolve(SkillRegistry::class)->isLoaded('test-skill'));
    }

    public function test_with_skill_instructions_composes_static_skill_dynamic_in_order()
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('ordered-skill', 'Ordered skill', 'Ordered instructions.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['ordered-skill'];
            }
        };

        $prompt = $agent->withSkillInstructions(
            'Base instructions...',
            'Runtime context goes last.'
        );

        $skillPosition = strpos($prompt, '<skill name="ordered-skill"');
        $runtimePosition = strpos($prompt, 'Runtime context goes last.');

        $this->assertNotFalse($skillPosition);
        $this->assertNotFalse($runtimePosition);
        $this->assertGreaterThan($skillPosition, $runtimePosition);
        $this->assertStringStartsWith('Base instructions...', $prompt);
        $this->assertStringEndsWith('Runtime context goes last.', $prompt);

        $this->deleteFixtureSkill('ordered-skill');
    }

    public function test_with_skill_instructions_without_dynamic_prompt_joins_cleanly()
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('join-skill', 'Join skill', 'Join instructions.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['join-skill'];
            }
        };

        $skillsBlock = $agent->skillInstructions();
        $prompt = $agent->withSkillInstructions('Base instructions...');

        $this->assertSame("Base instructions...\n\n{$skillsBlock}", $prompt);
        $this->assertStringNotContainsString("\n\n\n", $prompt);

        $this->deleteFixtureSkill('join-skill');
    }

    public function test_with_skill_instructions_handles_disabled_skills_without_skill_block()
    {
        config(['skills.enabled' => false]);

        $agent = new class
        {
            use Skillable;
        };

        $prompt = $agent->withSkillInstructions(
            'Base instructions...',
            'Runtime context goes last.'
        );

        $this->assertSame(
            "Base instructions...\n\nRuntime context goes last.",
            $prompt
        );
    }

    public function test_mixed_per_skill_modes_use_explicit_value_or_config_fallback()
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('explicit-full-skill', 'Explicit full skill', 'Show me fully.');
        $this->createFixtureSkill('fallback-lite-skill', 'Fallback lite skill', 'Hide me by default.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return [
                    'explicit-full-skill' => 'full',
                    'fallback-lite-skill',
                ];
            }
        };

        $instructions = $agent->skillInstructions();

        $this->assertStringContainsString('<skill name="explicit-full-skill">', $instructions);
        $this->assertStringContainsString('Show me fully.', $instructions);
        $this->assertStringContainsString('<skill name="fallback-lite-skill" description="Fallback lite skill" />', $instructions);
        $this->assertStringNotContainsString('Hide me by default.', $instructions);

        $this->deleteFixtureSkill('explicit-full-skill');
        $this->deleteFixtureSkill('fallback-lite-skill');
    }

    public function test_per_skill_alias_modes_are_supported()
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('lazy-skill', 'Lazy skill', 'Should stay hidden.');
        $this->createFixtureSkill('eager-skill', 'Eager skill', 'Should be visible.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return [
                    'lazy-skill' => 'lazy',
                    'eager-skill' => 'eager',
                ];
            }
        };

        $instructions = $agent->skillInstructions();

        $this->assertStringContainsString('description="Lazy skill"', $instructions);
        $this->assertStringNotContainsString('Should stay hidden.', $instructions);
        $this->assertStringContainsString('<skill name="eager-skill">', $instructions);
        $this->assertStringContainsString('Should be visible.', $instructions);

        $this->deleteFixtureSkill('lazy-skill');
        $this->deleteFixtureSkill('eager-skill');
    }

    public function test_per_skill_enum_modes_are_supported()
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('enum-skill', 'Enum skill', 'Enum full instructions.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return [
                    'enum-skill' => SkillInclusionMode::Full,
                ];
            }
        };

        $instructions = $agent->skillInstructions();

        $this->assertStringContainsString('<skill name="enum-skill">', $instructions);
        $this->assertStringContainsString('Enum full instructions.', $instructions);

        $this->deleteFixtureSkill('enum-skill');
    }

    public function test_invalid_per_skill_mode_logs_warning_and_falls_back_to_config()
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('invalid-mode-skill', 'Invalid mode skill', 'Should stay hidden by fallback.');

        Log::shouldReceive('warning')->atLeast()->once();

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return [
                    'invalid-mode-skill' => 'fast',
                ];
            }
        };

        $instructions = $agent->skillInstructions();

        $this->assertStringContainsString('description="Invalid mode skill"', $instructions);
        $this->assertStringNotContainsString('Should stay hidden by fallback.', $instructions);

        $this->deleteFixtureSkill('invalid-mode-skill');
    }

    private function createFixtureSkill(string $slug, string $description, string $instructions): void
    {
        $skillPath = __DIR__.'/../fixtures/skills/'.$slug;
        if (! File::exists($skillPath)) {
            File::makeDirectory($skillPath, 0755, true);
        }

        File::put($skillPath.'/SKILL.md', <<<EOT
---
name: {$slug}
description: {$description}
---
# Instructions
{$instructions}
EOT
        );
    }

    private function deleteFixtureSkill(string $slug): void
    {
        File::deleteDirectory(__DIR__.'/../fixtures/skills/'.$slug);
    }
}
