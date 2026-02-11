# Laravel AI SDK Skills

- [Introduction](#introduction)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Creating Skills](#creating-skills)
    - [Skill File Structure](#skill-file-structure)
    - [Artisan Commands](#artisan-commands)
- [Using Skills in Agents](#using-skills-in-agents)
    - [The Skillable Trait](#the-skillable-trait)
    - [Loading Skills by Path](#loading-skills-by-path)
- [Configuration](#configuration)
    - [Discovery Mode](#discovery-mode)
- [Agent Skills Protocol](#agent-skills-protocol)
    - [Progressive Disclosure](#progressive-disclosure)
    - [Tool Naming Convention](#tool-naming-convention)
    - [Canonical XML Format](#canonical-xml-format)
- [How It Works](#how-it-works)
- [Testing](#testing)

<a name="introduction"></a>
## Introduction

This package extends Laravel AI SDK with a high-performance skill system. Skills are reusable capability modules that provide instructions, tools, and context to your AI agents through a **Progressive Disclosure** mechanism.

Instead of embedding all logic in your agent class or bloating the context window with unused instructions, you define skills as separate markdown files. Each skill encapsulates its own instructions and tools. Agents discover what's available and load only what they need during the conversation.

<a name="installation"></a>
## Installation

Install the package via composer:

```shell
composer require anilcancakir/laravel-ai-sdk-skills
```

The service provider registers automatically. You should publish the configuration file:

```shell
php artisan vendor:publish --provider="AnilcanCakir\LaravelAiSdkSkills\SkillsServiceProvider"
```

<a name="quick-start"></a>
## Quick Start

Generate a new skill using the Artisan command:

```shell
php artisan skills:make doc-writer --description="Writes technical documentation"
```

This creates `resources/skills/doc-writer/SKILL.md`. Now, add the `Skillable` trait to your agent:

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
        return $this->skillTools();
    }
}
```

By calling `$this->skillTools()`, your agent automatically gains access to the `list_skills`, `skill`, and `skill_read` meta-tools, enabling dynamic capability discovery.

<a name="creating-skills"></a>
## Creating Skills

<a name="skill-file-structure"></a>
### Skill File Structure

Each skill lives in its own directory with a `SKILL.md` file. The file uses YAML frontmatter for metadata and markdown for the actual instructions:

```markdown
---
name: doc-writer
description: Writes technical documentation in a friendly style
version: 1.0.0
triggers:
  - write documentation
  - create docs
tools:
  - App\Ai\Tools\SearchDocs
mcp:
  - filesystem
constraints:
  - model: gemini-2.0-flash
---

# Documentation Writer

You are a technical documentation expert. When writing docs:

1. Use clear, concise language.
2. Include code examples.
3. Always use canonical XML tags for sections.
```

| Field | Required | Description |
|:------|:---------|:------------|
| `name` | Yes | Unique identifier (snake_case recommended). |
| `description` | Yes | Short explanation for progressive disclosure. |
| `version` | No | Semantic version of the skill. |
| `triggers` | No | Keywords that hint when this skill is relevant. |
| `tools` | No | Fully qualified class names of tools provided by this skill. |
| `mcp` | No | List of MCP (Model Context Protocol) dependencies. |
| `constraints` | No | List of runtime requirements (e.g., model versions). |

<a name="artisan-commands"></a>
### Artisan Commands

Use the `make` command to scaffold new skills quickly:

```shell
php artisan skills:make my-skill
```

You can also provide a description directly:

```shell
php artisan skills:make code-reviewer --description="Reviews code for best practices"
```

<a name="using-skills-in-agents"></a>
## Using Skills in Agents

<a name="the-skillable-trait"></a>
### The Skillable Trait

The `Skillable` trait handles the heavy lifting of loading and managing skills.

```php
public function instructions(): string
{
    return "Base instructions here...\n\n" . $this->skillInstructions();
}

public function tools(): iterable
{
    return array_merge(
        [new NativeTool],
        $this->skillTools()
    );
}
```

| Method | Returns | Description |
|:-------|:--------|:------------|
| `skillTools()` | `array` | Returns `list_skills`, `skill`, `skill_read`, and any tools from pre-loaded skills. |
| `skillInstructions(?string $mode)` | `string` | Combined instructions from loaded skills. Pass `'lite'` or `'full'` to override config. |
| `skills()` | `iterable` | Define which skills should be available to this agent. |

The `skillInstructions()` method accepts an optional `$mode` parameter to override the global `discovery_mode` config per-agent:

```php
public function instructions(): string
{
    // Uses global config (default: 'lite')
    return "Base instructions...\n\n" . $this->skillInstructions();
}

// Or override per-agent to always load full instructions
public function instructions(): string
{
    return "Base instructions...\n\n" . $this->skillInstructions('full');
}
```

<a name="loading-skills-by-path"></a>
### Loading Skills by Path

While loading by name is standard, you can also load skills by their absolute path:

```php
public function skills(): iterable
{
    return [
        'doc-writer',
        resource_path('custom/internal-skill'),
    ];
}
```

<a name="configuration"></a>
## Configuration

The `config/skills.php` file allows you to control the discovery process and global state.

```php
return [
    // Globally enable or disable the skill system
    'enabled' => env('SKILLS_ENABLED', true),

    // Discovery mode: 'lite' or 'full'
    'discovery_mode' => env('SKILLS_DISCOVERY_MODE', 'lite'),

    // Skill source mode: 'local', 'remote', or 'dual'
    'mode' => env('SKILLS_MODE', 'local'),

    // Directories where skills are discovered
    'paths' => [
        resource_path('skills'),
    ],

    // Remote discovery (only used when mode is 'remote' or 'dual')
    'remote' => [
        'url' => env('SKILLS_REMOTE_URL'),
        'token' => env('SKILLS_REMOTE_TOKEN'),
        'timeout' => env('SKILLS_REMOTE_TIMEOUT', 5),
    ],
];
```

> [!NOTE]
> When `enabled` is set to `false`, the `Skillable` trait gracefully returns empty tools and instructions without triggering discovery.

<a name="discovery-mode"></a>
### Discovery Mode

The `discovery_mode` setting controls how much skill content is injected into the agent's context when skills are pre-loaded via the `skills()` method.

| Mode | `skillInstructions()` Output | Token Usage | Agent Must Call `skill()` |
|:-----|:----------------------------|:------------|:--------------------------|
| `lite` | `<skill name="..." description="..." />` | Minimal | Yes, to get full instructions |
| `full` | `<skill name="...">full SKILL.md content</skill>` | Higher | No, instructions already loaded |

**Lite mode** (default) follows the Progressive Disclosure principle — agents see only what each skill is about. When the agent needs the full instructions, it calls the `skill('skill-name')` tool to load them on demand. This saves tokens for agents that may not use every pre-loaded skill.

**Full mode** eagerly injects the complete SKILL.md content for every pre-loaded skill. Best for agents with a focused skill set where you know every skill will be used.

You can override the global setting per-agent by passing a mode to `skillInstructions()`:

```php
// Always load full instructions for this specific agent
public function instructions(): string
{
    return "You are an expert...\n\n" . $this->skillInstructions('full');
}
```

<a name="agent-skills-protocol"></a>
## Agent Skills Protocol

The package implements a specialized protocol to ensure predictable interactions between the LLM and your codebase.

<a name="progressive-disclosure"></a>
### Progressive Disclosure

To prevent context window bloat, the system uses progressive disclosure. The `list_skills` tool doesn't just list names; its description dynamically injects an `<available_skills>` XML block. 

This allows the AI to see exactly what each skill does before deciding to load it. The `maxDescriptionSkills` config (default: 50) prevents the tool description itself from becoming too large.

<a name="tool-naming-convention"></a>
### Tool Naming Convention

All skill-related tools follow a strict `snake_case` naming convention. Core tools provided:

- `list_skills`: Discovery and metadata disclosure.
- `skill`: Dynamic loader that fetches instructions and tools.
- `skill_read`: Reads supplementary files from within a loaded skill's directory.

<a name="testing"></a>
## Testing

The package maintains high test coverage to ensure reliability across Laravel versions.

```shell
php artisan test plugins/laravel-ai-sdk-skills/tests/
```

Current status: **70 tests, 181 assertions** passing.

> [!NOTE]
> Phase 2 features including native MCP execution and sub-agent delegation are currently in the roadmap.
