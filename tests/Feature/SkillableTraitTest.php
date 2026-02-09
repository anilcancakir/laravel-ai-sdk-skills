<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Feature;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use AnilcanCakir\LaravelAiSdkSkills\Traits\Skillable;
use Illuminate\Support\Facades\File;

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
}
