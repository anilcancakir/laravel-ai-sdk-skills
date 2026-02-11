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

    public function test_defaults_for_new_properties(): void
    {
        $skill = new Skill(
            name: 'Test',
            description: 'Test',
            instructions: 'Test',
            tools: [],
            triggers: []
        );

        $this->assertNull($skill->version);
    }

    public function test_it_can_be_instantiated_with_version(): void
    {
        $skill = new Skill(
            name: 'Test',
            description: 'Test',
            instructions: 'Test',
            tools: [],
            triggers: [],
            version: '2.0.0'
        );

        $this->assertEquals('2.0.0', $skill->version);
    }

    public function test_basepath_property(): void
    {
        $skill = new Skill(
            name: 'Local Skill',
            description: 'desc',
            instructions: 'inst',
            tools: [],
            triggers: [],
            basePath: '/var/skills/my-skill'
        );

        $this->assertEquals('/var/skills/my-skill', $skill->basePath);
    }

    public function test_slug_with_special_characters(): void
    {
        $skill1 = new Skill(name: 'Über Skill', description: 'desc', instructions: 'inst', tools: [], triggers: []);
        $this->assertEquals('uber-skill', $skill1->slug());

        $skill2 = new Skill(name: 'my.skill.v2', description: 'desc', instructions: 'inst', tools: [], triggers: []);
        $this->assertEquals('myskillv2', $skill2->slug());

        $skill3 = new Skill(name: 'multi   space', description: 'desc', instructions: 'inst', tools: [], triggers: []);
        $this->assertEquals('multi-space', $skill3->slug());
    }

    public function test_it_returns_reference_files_from_base_path(): void
    {
        $tempDir = sys_get_temp_dir().'/skill_test_'.uniqid();
        mkdir($tempDir);
        mkdir($tempDir.'/references');

        try {
            file_put_contents($tempDir.'/SKILL.md', '# Skill');
            file_put_contents($tempDir.'/references/utilities.md', 'utils');
            file_put_contents($tempDir.'/references/theme.md', 'theme');
            file_put_contents($tempDir.'/notes.txt', 'notes');

            $skill = new Skill(
                name: 'Test',
                description: 'desc',
                instructions: 'inst',
                tools: [],
                triggers: [],
                basePath: $tempDir
            );

            $this->assertEquals(['notes.txt', 'references/theme.md', 'references/utilities.md'], $skill->referenceFiles());
            $this->assertTrue($skill->hasReferenceFiles());
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function test_it_returns_empty_reference_files_when_no_base_path(): void
    {
        $skill = new Skill(
            name: 'Test',
            description: 'desc',
            instructions: 'inst',
            tools: [],
            triggers: [],
            basePath: null
        );

        $this->assertEquals([], $skill->referenceFiles());
        $this->assertFalse($skill->hasReferenceFiles());
    }

    public function test_it_returns_empty_reference_files_when_no_files_exist(): void
    {
        $tempDir = sys_get_temp_dir().'/skill_test_'.uniqid();
        mkdir($tempDir);

        try {
            file_put_contents($tempDir.'/SKILL.md', '# Skill');

            $skill = new Skill(
                name: 'Test',
                description: 'desc',
                instructions: 'inst',
                tools: [],
                triggers: [],
                basePath: $tempDir
            );

            $this->assertEquals([], $skill->referenceFiles());
            $this->assertFalse($skill->hasReferenceFiles());
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function test_it_excludes_non_allowed_extensions(): void
    {
        $tempDir = sys_get_temp_dir().'/skill_test_'.uniqid();
        mkdir($tempDir);

        try {
            file_put_contents($tempDir.'/image.png', '');
            file_put_contents($tempDir.'/style.css', '');
            file_put_contents($tempDir.'/code.php', '');
            file_put_contents($tempDir.'/valid.md', '');

            $skill = new Skill(
                name: 'Test',
                description: 'desc',
                instructions: 'inst',
                tools: [],
                triggers: [],
                basePath: $tempDir
            );

            $this->assertEquals(['valid.md'], $skill->referenceFiles());
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = scandir($path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filePath = $path.DIRECTORY_SEPARATOR.$file;
            if (is_dir($filePath)) {
                $this->removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        rmdir($path);
    }
}
