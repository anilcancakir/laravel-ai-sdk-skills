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

    public function test_it_returns_null_on_missing_description()
    {
        Log::shouldReceive('warning')->once();

        $markdown = <<<'MD'
---
name: no-desc
---
Body
MD;

        $this->assertNull(SkillParser::parse($markdown));
    }

    public function test_it_returns_null_when_tools_is_not_array()
    {
        Log::shouldReceive('warning')->once();

        $markdown = <<<'MD'
---
name: bad-tools
description: A skill
tools: "not-an-array"
---
Body
MD;

        $this->assertNull(SkillParser::parse($markdown));
    }

    public function test_it_normalizes_crlf_line_endings()
    {
        $markdownLf = "---\nname: crlf-test\ndescription: CRLF skill\n---\nInstructions here.";
        $markdownCrlf = "---\r\nname: crlf-test\r\ndescription: CRLF skill\r\n---\r\nInstructions here.";

        $skillLf = SkillParser::parse($markdownLf);
        $skillCrlf = SkillParser::parse($markdownCrlf);

        $this->assertInstanceOf(Skill::class, $skillLf);
        $this->assertInstanceOf(Skill::class, $skillCrlf);
        $this->assertEquals($skillLf->name, $skillCrlf->name);
        $this->assertEquals($skillLf->description, $skillCrlf->description);
        $this->assertEquals($skillLf->instructions, $skillCrlf->instructions);
    }

    public function test_it_strips_yaml_document_end_markers()
    {
        $markdown = <<<'MD'
---
name: end-marker
description: Has end marker
...
---
Body content.
MD;

        $skill = SkillParser::parse($markdown);

        $this->assertInstanceOf(Skill::class, $skill);
        $this->assertEquals('end-marker', $skill->name);
        $this->assertEquals('Body content.', $skill->instructions);
    }

    public function test_it_passes_basepath_to_skill()
    {
        $markdown = <<<'MD'
---
name: skill
description: A skill
---
Instructions.
MD;

        $skill = SkillParser::parse($markdown, '/custom/path');

        $this->assertInstanceOf(Skill::class, $skill);
        $this->assertEquals('/custom/path', $skill->basePath);
    }

    public function test_it_returns_null_when_closing_delimiter_missing()
    {
        Log::shouldReceive('warning')->once();

        $markdown = "---\nname: x\ndescription: y\nBody without closing";

        $this->assertNull(SkillParser::parse($markdown));
    }
}
