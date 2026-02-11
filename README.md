# Laravel AI SDK Skills

This package extends the Laravel AI SDK with a high-performance skill system. Skills are reusable capability modules that provide instructions, tools, and context to your AI agents through a **Progressive Disclosure** mechanism.

Instead of embedding all logic in your agent class or bloating the context window with unused instructions, you define skills as separate markdown files. Each skill encapsulates its own instructions and tools. Agents discover what's available and load only what they need during the conversation.

## Installation

Install the package via composer:

```shell
composer require anilcancakir/laravel-ai-sdk-skills
```

The service provider registers automatically. You should publish the configuration file to customize discovery paths and modes:

```shell
php artisan vendor:publish --provider="AnilcanCakir\LaravelAiSdkSkills\SkillsServiceProvider"
```

## Quick Start

Let's look at how quickly you can add a new capability to your agent. First, generate a new skill:

```shell
php artisan skills:make doc-writer --description="Writes technical documentation"
```

This creates `resources/skills/doc-writer/SKILL.md`. Now, add the `Skillable` trait to your agent and register the skill:

```php
<?php

namespace App\Ai\Agents;

use AnilcanCakir\LaravelAiSdkSkills\Traits\Skillable;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;

class Assistant implements Agent, HasTools
{
    use Skillable;

    public function skills(): iterable
    {
        return ['doc-writer'];
    }

    public function instructions(): string
    {
        return "Base instructions...\n\n" . $this->skillInstructions();
    }

    public function tools(): iterable
    {
        return $this->skillTools();
    }
}
```

By calling `$this->skillTools()`, your agent automatically gains access to meta-tools like `list_skills` and `skill`, enabling dynamic discovery.

## The Skill Format

Each skill lives in its own directory with a `SKILL.md` file. It uses YAML frontmatter for metadata and standard markdown for the instructions.

```markdown
---
name: doc-writer
description: Writes technical documentation in a friendly style
tools:
  - App\Ai\Tools\SearchDocs
---

# Documentation Writer

You are a technical documentation expert. Use clear language and provide code examples.
```

| Field | Required | Description |
|:------|:---------|:------------|
| `name` | Yes | Unique identifier (snake_case). |
| `description` | Yes | Short explanation used for discovery. |
| `tools` | No | Fully qualified class names of tools provided by this skill. |

## Core Concepts

### Progressive Disclosure

To prevent context window bloat, we use progressive disclosure. The AI only sees a list of available skills and their short descriptions. It "discovers" the full instructions only when it decides a skill is necessary for the current task.

### Discovery Modes

By default, skills are loaded from your local `resources/skills` directory. You can configure the search paths in `config/skills.php`.

### Lite vs Full Mode

The `discovery_mode` controls how much information is injected into the initial prompt:

- **Lite** (Default): Injects only `<skill name="..." description="..." />` tags. Minimal tokens.
- **Full**: Injects the complete `SKILL.md` content immediately. Best for agents with very specific, small skill sets.

## Artisan Commands

We've provided a few commands to help you manage your skills:

```shell
# Create a new skill scaffold
php artisan skills:make my-skill

# List all discovered skills in a table
php artisan skills:list

# Flush the skill discovery cache
php artisan skills:clear
```

## Built-in Tools

When you use the `Skillable` trait, your agent gets these tools automatically:

- `list_skills`: Returns a list of all available skills the agent can load.
- `skill`: Loads the full instructions and tools for a specific skill into the conversation.
- `skill_read`: Safely reads supplementary files (like `/docs/api.md`) from within a loaded skill's directory.

> [!NOTE]
> The `skill_read` tool is restricted to the skill's own directory, ensuring your agent can't wander off into sensitive parts of your filesystem.

## Testing

The package is built with testability in mind and maintains high coverage.

```shell
php artisan test
```

Current status: **84 tests, 200+ assertions** passing.