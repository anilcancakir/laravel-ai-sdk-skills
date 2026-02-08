<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Support\Skill;
use AnilcanCakir\LaravelAiSdkSkills\Support\SkillParser;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use Illuminate\Support\Facades\Log;

class SkillParserTest extends TestCase
{
    public function test_it_parses_valid_skill_markdown()
    {
        $markdown = <<<'MD'
---
name: test-skill
description: A test skill
tools:
  - test_tool
---
This is the instructions.
MD;

        $skill = SkillParser::parse($markdown);

        $this->assertInstanceOf(Skill::class, $skill);
        $this->assertEquals('test-skill', $skill->name);
        $this->assertEquals('A test skill', $skill->description);
        $this->assertEquals(['test_tool'], $skill->tools);
        $this->assertEquals('This is the instructions.', $skill->instructions);
    }

    public function test_it_handles_missing_tools_defaulting_to_empty_array()
    {
        $markdown = <<<'MD'
---
name: test-skill
description: A test skill
---
Instructions only.
MD;

        $skill = SkillParser::parse($markdown);

        $this->assertEmpty($skill->tools);
    }

    public function test_it_returns_null_and_logs_error_on_invalid_yaml()
    {
        Log::shouldReceive('warning')->once();

        $markdown = <<<'MD'
---
name: : invalid yaml
---
Body
MD;

        $skill = SkillParser::parse($markdown);

        $this->assertNull($skill);
    }

    public function test_it_returns_null_when_frontmatter_delimiter_is_missing()
    {
        Log::shouldReceive('warning')->once();

        $markdown = 'Just some markdown text without frontmatter.';

        $skill = SkillParser::parse($markdown);

        $this->assertNull($skill);
    }

    public function test_it_trims_whitespace_from_instructions()
    {
        $markdown = <<<'MD'
---
name: test
description: desc
tools: []
---

  Instructions with whitespace  

MD;
        $skill = SkillParser::parse($markdown);
        $this->assertEquals('Instructions with whitespace', $skill->instructions);
    }

    public function test_it_logs_warning_on_missing_name()
    {
        Log::shouldReceive('warning')->once();

        $markdown = <<<'MD'
---
description: No name
---
Body
MD;
        $this->assertNull(SkillParser::parse($markdown));
    }
}
