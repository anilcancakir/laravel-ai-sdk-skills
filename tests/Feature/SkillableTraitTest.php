<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Feature;

use AnilcanCakir\LaravelAiSdkSkills\Enums\SkillInclusionMode;
use AnilcanCakir\LaravelAiSdkSkills\Support\Prompt;
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

    public function test_with_skill_instructions_without_any_prompts_returns_only_skills()
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('bare-skill', 'Bare skill', 'Bare instructions.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['bare-skill'];
            }
        };

        $skillsBlock = $agent->skillInstructions();
        $prompt = $agent->withSkillInstructions();

        $this->assertSame($skillsBlock, $prompt);
        $this->assertStringContainsString('bare-skill', $prompt);

        $this->deleteFixtureSkill('bare-skill');
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

    public function test_with_skill_instructions_includes_only_full_instruction_bodies_for_mixed_modes()
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('prompt-full-skill', 'Prompt full skill', 'Full skill instructions in system prompt.');
        $this->createFixtureSkill('prompt-lite-skill', 'Prompt lite skill', 'Lite skill instructions must stay out.');
        $this->createFixtureSkill('prompt-fallback-skill', 'Prompt fallback skill', 'Fallback skill instructions must stay out.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return [
                    'prompt-full-skill' => 'full',
                    'prompt-lite-skill' => 'lite',
                    'prompt-fallback-skill',
                ];
            }
        };

        $prompt = $agent->withSkillInstructions(
            'Base instructions...',
            'Runtime context goes last.'
        );

        $this->assertStringContainsString('<skill name="prompt-full-skill">', $prompt);
        $this->assertStringContainsString('Full skill instructions in system prompt.', $prompt);
        $this->assertStringNotContainsString('Lite skill instructions must stay out.', $prompt);
        $this->assertStringNotContainsString('Fallback skill instructions must stay out.', $prompt);
        $this->assertStringContainsString('<skill name="prompt-lite-skill" description="Prompt lite skill" />', $prompt);
        $this->assertStringContainsString('<skill name="prompt-fallback-skill" description="Prompt fallback skill" />', $prompt);

        $this->deleteFixtureSkill('prompt-full-skill');
        $this->deleteFixtureSkill('prompt-lite-skill');
        $this->deleteFixtureSkill('prompt-fallback-skill');
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

    /**
     * -------------------------------------------------------
     * BACKWARD COMPATIBILITY TESTS
     * -------------------------------------------------------
     * These tests simulate the exact patterns a v1.0.0 user
     * would write. They MUST pass without modification.
     * -------------------------------------------------------
     */
    public function test_bc_simple_string_array_skills_still_works()
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('bc-skill-a', 'Skill A', 'Instructions A');
        $this->createFixtureSkill('bc-skill-b', 'Skill B', 'Instructions B');

        // v1.0.0 pattern: simple string array
        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['bc-skill-a', 'bc-skill-b'];
            }
        };

        $tools = $agent->skillTools();
        $this->assertNotEmpty($tools);

        $instructions = $agent->skillInstructions();
        $this->assertStringContainsString('bc-skill-a', $instructions);
        $this->assertStringContainsString('bc-skill-b', $instructions);

        $registry = resolve(SkillRegistry::class);
        $this->assertTrue($registry->isLoaded('bc-skill-a'));
        $this->assertTrue($registry->isLoaded('bc-skill-b'));

        $this->deleteFixtureSkill('bc-skill-a');
        $this->deleteFixtureSkill('bc-skill-b');
    }

    public function test_bc_skill_instructions_with_no_args()
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('bc-noarg', 'No arg skill', 'Full body.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['bc-noarg'];
            }
        };

        // v1.0.0 pattern: no args
        $result = $agent->skillInstructions();
        $this->assertIsString($result);
        $this->assertStringContainsString('bc-noarg', $result);

        $this->deleteFixtureSkill('bc-noarg');
    }

    public function test_bc_skill_instructions_with_string_mode_lite()
    {
        config(['skills.discovery_mode' => 'full']);
        $this->createFixtureSkill('bc-mode', 'Mode skill', 'Should be hidden in lite.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['bc-mode'];
            }
        };

        // v1.0.0 pattern: string 'lite' override
        $lite = $agent->skillInstructions('lite');
        $this->assertStringNotContainsString('Should be hidden in lite.', $lite);
        $this->assertStringContainsString('description="Mode skill"', $lite);

        $this->deleteFixtureSkill('bc-mode');
    }

    public function test_bc_skill_instructions_with_string_mode_full()
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('bc-full', 'Full skill', 'Visible in full mode.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['bc-full'];
            }
        };

        // v1.0.0 pattern: string 'full' override
        $full = $agent->skillInstructions('full');
        $this->assertStringContainsString('Visible in full mode.', $full);
        $this->assertStringContainsString('<skill name="bc-full">', $full);

        $this->deleteFixtureSkill('bc-full');
    }

    public function test_bc_concat_pattern_still_works()
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('bc-concat', 'Concat skill', 'Concat body.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['bc-concat'];
            }

            // v1.0.0 pattern: manual string concatenation
            public function instructions(): string
            {
                return "Base instructions...\n\n".$this->skillInstructions();
            }
        };

        $result = $agent->instructions();
        $this->assertStringStartsWith('Base instructions...', $result);
        $this->assertStringContainsString('bc-concat', $result);
        $this->assertStringContainsString("\n\n", $result);

        $this->deleteFixtureSkill('bc-concat');
    }

    public function test_bc_empty_skills_returns_empty()
    {
        // v1.0.0 pattern: default empty skills()
        $agent = new class
        {
            use Skillable;
        };

        $tools = $agent->skillTools();
        $this->assertNotEmpty($tools); // meta-tools still present

        $instructions = $agent->skillInstructions();
        $this->assertEmpty($instructions);
    }

    public function test_bc_skill_tools_returns_meta_tools()
    {
        $this->createFixtureSkill('bc-tools', 'Tools skill', 'Has tools.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['bc-tools'];
            }
        };

        // v1.0.0 pattern: skillTools() returns meta-tools + skill tools
        $tools = $agent->skillTools();
        $this->assertIsArray($tools);
        $this->assertNotEmpty($tools);

        // Meta-tools should always be present
        $toolNames = array_map(fn ($t) => $t->name(), $tools);
        $this->assertContains('list_skills', $toolNames);
        $this->assertContains('skill', $toolNames);
        $this->assertContains('skill_read', $toolNames);

        $this->deleteFixtureSkill('bc-tools');
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

    /**
     * -------------------------------------------------------
     * composeInstructions() Tests
     * -------------------------------------------------------
     */
    public function test_compose_instructions_with_plain_strings(): void
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('ci-plain', 'CI plain skill', 'CI plain body.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['ci-plain'];
            }
        };

        $prompt = $agent->composeInstructions('Static part.', 'Dynamic part.');
        $withSkill = $agent->withSkillInstructions('Static part.', 'Dynamic part.');

        $this->assertSame($withSkill, $prompt);
        $this->assertStringStartsWith('Static part.', $prompt);
        $this->assertStringEndsWith('Dynamic part.', $prompt);

        $this->deleteFixtureSkill('ci-plain');
    }

    public function test_compose_instructions_with_prompt_text_objects(): void
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('ci-text', 'CI text skill', 'CI text body.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['ci-text'];
            }
        };

        $prompt = $agent->composeInstructions(
            Prompt::text('You are {{role}}', ['role' => 'coach']),
            Prompt::text('User: {{name}}', ['name' => 'Alice']),
        );

        $this->assertStringStartsWith('You are coach', $prompt);
        $this->assertStringEndsWith('User: Alice', $prompt);
        $this->assertStringContainsString('ci-text', $prompt);

        $this->deleteFixtureSkill('ci-text');
    }

    public function test_compose_instructions_with_prompt_file(): void
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('ci-file', 'CI file skill', 'CI file body.');

        $dir = storage_path('temp-prompts');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir.'/compose-test.md';
        File::put($path, 'Static from file for {{env}}.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['ci-file'];
            }
        };

        $prompt = $agent->composeInstructions(
            Prompt::file($path, ['env' => 'production']),
            'Dynamic runtime.',
        );

        $this->assertStringStartsWith('Static from file for production.', $prompt);
        $this->assertStringEndsWith('Dynamic runtime.', $prompt);
        $this->assertStringContainsString('ci-file', $prompt);

        File::delete($path);
        $this->deleteFixtureSkill('ci-file');
    }

    public function test_compose_instructions_with_mixed_types(): void
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('ci-mixed', 'CI mixed skill', 'CI mixed body.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['ci-mixed'];
            }
        };

        // string static + Prompt dynamic
        $prompt1 = $agent->composeInstructions(
            'Plain static.',
            Prompt::text('Dynamic {{mode}}', ['mode' => 'active']),
        );

        $this->assertStringStartsWith('Plain static.', $prompt1);
        $this->assertStringEndsWith('Dynamic active', $prompt1);

        // Prompt static + string dynamic
        $prompt2 = $agent->composeInstructions(
            Prompt::text('Prompt {{side}}', ['side' => 'left']),
            'String dynamic.',
        );

        $this->assertStringStartsWith('Prompt left', $prompt2);
        $this->assertStringEndsWith('String dynamic.', $prompt2);

        $this->deleteFixtureSkill('ci-mixed');
    }

    public function test_compose_instructions_without_args_returns_only_skills(): void
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('ci-noarg', 'CI noarg skill', 'CI noarg body.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['ci-noarg'];
            }
        };

        $skillsOnly = $agent->skillInstructions();
        $composed = $agent->composeInstructions();

        $this->assertSame($skillsOnly, $composed);

        $this->deleteFixtureSkill('ci-noarg');
    }

    public function test_compose_instructions_with_disabled_skills(): void
    {
        config(['skills.enabled' => false]);

        $agent = new class
        {
            use Skillable;
        };

        $prompt = $agent->composeInstructions(
            Prompt::text('Static {{x}}', ['x' => 'A']),
            Prompt::text('Dynamic {{y}}', ['y' => 'B']),
        );

        $this->assertSame("Static A\n\nDynamic B", $prompt);
    }

    public function test_compose_instructions_ordering_static_skills_dynamic(): void
    {
        config(['skills.discovery_mode' => 'lite']);
        $this->createFixtureSkill('ci-order', 'CI order skill', 'CI order body.');

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['ci-order'];
            }
        };

        $prompt = $agent->composeInstructions(
            Prompt::text('FIRST_SEGMENT'),
            Prompt::text('LAST_SEGMENT'),
        );

        $staticPos = strpos($prompt, 'FIRST_SEGMENT');
        $skillPos = strpos($prompt, 'ci-order');
        $dynamicPos = strpos($prompt, 'LAST_SEGMENT');

        $this->assertNotFalse($staticPos);
        $this->assertNotFalse($skillPos);
        $this->assertNotFalse($dynamicPos);
        $this->assertLessThan($skillPos, $staticPos);
        $this->assertLessThan($dynamicPos, $skillPos);

        $this->deleteFixtureSkill('ci-order');
    }

    private function deleteFixtureSkill(string $slug): void
    {
        File::deleteDirectory(__DIR__.'/../fixtures/skills/'.$slug);
    }
}
