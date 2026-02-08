<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Feature;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use AnilcanCakir\LaravelAiSdkSkills\Traits\Skillable;
use Illuminate\Support\Facades\File;

class SkillableTraitTest extends TestCase
{
    public function test_skills_are_loaded_on_boot()
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

        // Assert: Verify skill is loaded
        $this->assertTrue(resolve(SkillRegistry::class)->isLoaded('test-skill'));

        // Cleanup
        File::deleteDirectory($skillPath);
    }

    public function test_load_by_path()
    {
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
                $this->bootSkillable();
            }

            public function skills(): iterable
            {
                return [$this->path];
            }
        };

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
}
