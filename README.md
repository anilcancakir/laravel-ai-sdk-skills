<p align="center"><img src="/art/banner.jpeg" alt="Laravel AI SDK Skills"></p>

<p align="center">
<a href="https://github.com/anilcancakir/laravel-ai-sdk-skills/actions"><img src="https://github.com/anilcancakir/laravel-ai-sdk-skills/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/anilcancakir/laravel-ai-sdk-skills"><img src="https://img.shields.io/packagist/dt/anilcancakir/laravel-ai-sdk-skills" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/anilcancakir/laravel-ai-sdk-skills"><img src="https://img.shields.io/packagist/v/anilcancakir/laravel-ai-sdk-skills" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/anilcancakir/laravel-ai-sdk-skills"><img src="https://img.shields.io/packagist/l/anilcancakir/laravel-ai-sdk-skills" alt="License"></a>
</p>

# Laravel AI SDK Skills

This package extends the Laravel AI SDK with a high-performance skill system. Skills are reusable capability modules that provide instructions, tools, and context to your AI agents through a **Progressive Disclosure** mechanism.

Instead of embedding all logic in your agent class or bloating the context window with unused instructions, you define skills as separate markdown files. Each skill encapsulates its own instructions and tools. Agents discover what's available and load only what they need during the conversation.

For a detailed walkthrough with real-world examples, check out the [announcement article on Medium](https://medium.com/@anilcan/level-up-your-laravel-ai-agents-with-modular-skills-39da3fe9fe4b).

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
---

# Documentation Writer

You are a technical documentation expert. Use clear language and provide code examples.
```

| Field         | Required | Description                           |
|:--------------|:---------|:--------------------------------------|
| `name`        | Yes      | Unique identifier (snake_case).       |
| `description` | Yes      | Short explanation used for discovery.  |

## Core Concepts

### Progressive Disclosure

To prevent context window bloat, we use progressive disclosure. The AI only sees a list of available skills and their short descriptions. It "discovers" the full instructions only when it decides a skill is necessary for the current task.

### Discovery Modes

By default, skills are loaded from your local `resources/skills` directory. You can configure the search paths in `config/skills.php`.

### Lite vs Full Mode

Each skill can be injected in **lite** or **full** mode:

- **Lite** (Default): Injects only `<skill name="..." description="..." />` tags. Minimal tokens.
- **Full**: Injects the complete `SKILL.md` content immediately. Best for agents with very specific, small skill sets.

`discovery_mode` in `config/skills.php` sets the global default inclusion strategy for all skills.

### Caching

Skill discovery results are cached automatically in production. In `local` and `testing` environments, caching is disabled by default so your changes are picked up immediately.

You can override this behavior via environment variables:

```env
SKILLS_CACHE_ENABLED=true    # Force cache on (even in local)
SKILLS_CACHE_STORE=file      # Use a specific cache store instead of the default
```

Run `php artisan skills:clear` to flush the cache manually.

## Advanced Usage

### Per-Skill Inclusion Modes

You can override the global `discovery_mode` on a per-skill basis. This is useful when certain skills should always be fully loaded while others stay in lite mode:

```php
use AnilcanCakir\LaravelAiSdkSkills\Enums\SkillInclusionMode;

public function skills(): iterable
{
    return [
        'style-guide' => SkillInclusionMode::Full,  // always inject full instructions
        'doc-writer' => SkillInclusionMode::Lite,    // always inject lite
        'qa-checker',                                 // uses config('skills.discovery_mode')
    ];
}
```

### Prompt Caching with `withSkillInstructions()`

For optimal prompt caching, you can use `withSkillInstructions()` to structure your system prompt. It places static content first, skill instructions in the middle, and dynamic (per-conversation) content last:

```php
public function instructions(): string
{
    return $this->withSkillInstructions(
        staticPrompt: "You are a helpful assistant...",
        dynamicPrompt: "Current user context: {$this->context}"
    );
}
```

This ordering maximizes prefix cache hits across conversations, improving responsiveness and reducing token costs. Both parameters are optional — calling `$this->withSkillInstructions()` with no arguments returns just the skill instructions.

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

```shell
php artisan test
```
