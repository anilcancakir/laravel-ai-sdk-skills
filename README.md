# Laravel AI SDK Skills

A skill system for Laravel AI SDK that enables AI agents to use specialized, reusable capabilities.

## Introduction

This package extends Laravel AI SDK with a skill system. Skills are reusable capability modules that provide instructions, tools, and context to your AI agents.

Instead of embedding all logic in your agent class, you define skills as separate markdown files. Each skill encapsulates its own instructions and tools. Agents load only what they need.

## Installation

```shell
composer require anilcancakir/laravel-ai-sdk-skills
```

The service provider registers automatically. To publish the configuration:

```shell
php artisan vendor:publish --provider="AnilcanCakir\LaravelAiSdkSkills\SkillsServiceProvider"
```

## Quick Start

Generate a new skill:

```shell
php artisan skills:make doc-writer --description="Writes technical documentation"
```

This creates `resources/skills/doc-writer/SKILL.md`. Add the skill to your agent:

```php
<?php

namespace App\Ai\Agents;

use AnilcanCakir\LaravelAiSdkSkills\Traits\Skillable;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;

class MyAssistant implements Agent, HasTools
{
    use Skillable;

    public function skills(): iterable
    {
        return ['doc-writer'];
    }

    public function tools(): iterable
    {
        return array_merge(
            [], // Your native tools
            $this->skillTools()
        );
    }
}
```

The agent now has access to the doc-writer skill's instructions and tools.

## Creating Skills

### Skill File Structure

Each skill lives in its own directory with a `SKILL.md` file. The file uses YAML frontmatter for metadata and markdown for instructions:

```markdown
---
name: doc-writer
description: Writes technical documentation in a friendly style
triggers:
  - write documentation
  - create docs
  - document this
tools:
  - App\Ai\Tools\SearchDocs
  - App\Ai\Tools\WriteFile
---

# Documentation Writer

You are a technical documentation expert. When writing docs:

1. Use clear, concise language
2. Include code examples
3. Add helpful notes and warnings

Always start with an introduction explaining what the reader will learn.
```

| Field | Required | Description |
|:------|:---------|:------------|
| `name` | Yes | Unique identifier for the skill |
| `description` | Yes | Short explanation shown in skill listings |
| `triggers` | No | Keywords that hint when this skill is relevant |
| `tools` | No | Fully qualified class names of tools this skill provides |

The markdown body becomes the skill's instructions, injected into your agent's context when loaded.

### Using Artisan Command

```shell
php artisan skills:make my-skill
```

With a custom description:

```shell
php artisan skills:make code-reviewer --description="Reviews code for best practices"
```

## Using Skills in Agents

### The Skillable Trait

Add the `Skillable` trait to your agent and define which skills it should use:

```php
<?php

namespace App\Ai\Agents;

use AnilcanCakir\LaravelAiSdkSkills\Traits\Skillable;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;

class WindAssistant implements Agent, HasTools
{
    use Skillable;

    public function skills(): iterable
    {
        return [
            'doc-writer',
            'code-reviewer',
        ];
    }

    public function tools(): iterable
    {
        return array_merge(
            [new SearchDocs],   // Agent's native tools
            $this->skillTools() // Tools from loaded skills
        );
    }

    public function instructions(): string
    {
        return "You are a helpful assistant.\n\n" . $this->skillInstructions();
    }
}
```

The trait provides three methods:

| Method | Returns | Description |
|:-------|:--------|:------------|
| `skillTools()` | `array` | All tools from loaded skills, plus `ListSkills` and `LoadSkill` meta-tools |
| `skillInstructions()` | `string` | Combined instructions from all loaded skills |
| `skills()` | `iterable` | Override to define which skills your agent uses |

### Loading Skills by Path

Skills can be loaded directly by path for package-specific or non-standard locations:

```php
public function skills(): iterable
{
    return [
        'doc-writer',                                    // By name
        resource_path('skills/my-custom-skill'),         // By path
        base_path('packages/my-package/skills/helper'),  // Another path
    ];
}
```

## Configuration

After publishing, configure `config/skills.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Skills
    |--------------------------------------------------------------------------
    */
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Skill Paths
    |--------------------------------------------------------------------------
    |
    | Directories where skills are discovered.
    |
    */
    'paths' => [
        resource_path('skills'),
    ],
];
```

Multiple discovery paths are supported:

```php
'paths' => [
    resource_path('skills'),
    base_path('packages/shared-skills'),
],
```

Skills are discovered in order. If two skills share the same name, the later path takes precedence.

## Artisan Commands

| Command | Description |
|:--------|:------------|
| `skills:list` | Show all discovered skills |
| `skills:make {name}` | Create a new skill |
| `skills:clear` | Clear the skill discovery cache |

Example output from `skills:list`:

```
+---------------+----------------------------------+
| Name          | Description                      |
+---------------+----------------------------------+
| doc-writer    | Writes technical documentation   |
| code-reviewer | Reviews code for best practices  |
+---------------+----------------------------------+
```

## How It Works

The skill system operates through four components:

1. **Discovery**: `SkillDiscovery` scans configured paths for `SKILL.md` files
2. **Parsing**: `SkillParser` extracts YAML frontmatter and markdown instructions
3. **Registry**: `SkillRegistry` manages which skills are loaded for the current request
4. **Integration**: The `Skillable` trait boots skills when your agent is instantiated

Agents also receive two meta-tools:

- **ListSkills**: Shows available skills the agent can load
- **LoadSkill**: Dynamically loads a skill at runtime

This enables agents to discover and load skills on-demand during a conversation.

## Testing

Run the test suite:

```shell
php artisan test plugins/laravel-ai-sdk-skills/tests/
```

Or with PHPUnit directly:

```shell
./vendor/bin/phpunit plugins/laravel-ai-sdk-skills/tests/
```
