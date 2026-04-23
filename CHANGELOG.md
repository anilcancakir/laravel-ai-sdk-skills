# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] - v1.2.0

### Breaking Changes
- **Requires `laravel/ai ^0.6.3`** (was `^0.1.3`). The v0.1.x-v0.5.x line is no longer supported.
- **Minimum PHP is now `^8.3`** (was `^8.2`). Matches the `laravel/ai` v0.6 baseline.
- **Minimum Laravel is now `^12.0`** (was `^11.0`). Laravel 13 is also supported.
- **Tool class renames**:
  - `Tools\SkillLoader` is now `Tools\Skill`.
  - `Tools\SkillReferenceReader` is now `Tools\SkillRead`.
  - `Tools\ListSkills` is unchanged.
- **LLM-facing tool names are PascalCase**: `ListSkills`, `Skill`, `SkillRead`. Previously, the custom `name()` method returned `list_skills`, `skill`, `skill_read`, but upstream gateways derive the LLM-facing name from `class_basename()`, so the snake_case names were never actually seen by the LLM. If your agent prompts, docs, or fixtures reference the old names, update them to the PascalCase equivalents.

### Changed
- `laravel/ai` moved from `require-dev` to `require`. The package needs it at runtime to implement the `Tool` contract.
- `orchestra/testbench` bumped to `^10.0|^11.0`, `phpunit/phpunit` bumped to `^11.0|^12.0`.

### Removed
- `Tool::name()` method on meta-tool classes. Upstream uses `class_basename()` for tool naming, so the method was unused.
- `tests/Stubs/AiStubs.php` fictional `Tool` interface stub. Tests now exercise the real `Laravel\Ai\Contracts\Tool` contract via the runtime dependency.

### CI
- GitHub Actions matrix expanded to PHP 8.3/8.4 x Laravel 12/13.

## [v1.1.0](https://github.com/anilcancakir/laravel-ai-sdk-skills/releases/tag/v1.1.0) - 2026-02-22
### Added
- **Prompt Value Object** (`Support\Prompt`): Immutable, `Stringable` value object for composing AI agent prompt content from multiple sources:
  - `Prompt::text()` — Inline text with `{{key}}` variable binding.
  - `Prompt::file()` — File-based templates with variable binding.
  - `Prompt::view()` — Full Blade view rendering with data passing.
- **`composeInstructions()` method** on the `Skillable` trait: Accepts `string|Prompt` for both static and dynamic prompt segments while maintaining the same Static → Skills → Dynamic ordering as `withSkillInstructions()` for optimal prompt caching.
### Notes
- Fully backward compatible with v1.0.x — no changes to existing method signatures.
- `withSkillInstructions()` remains unchanged and continues to work as before.
## [v1.0.1](https://github.com/anilcancakir/laravel-ai-sdk-skills/releases/tag/v1.0.1) - 2026-02-22

### Changed
* Permit symfony/yaml version v7.x OR v8.x by @GregPeden in https://github.com/anilcancakir/laravel-ai-sdk-skills/pull/2
* Expose config controls for caching behavior by @GregPeden in https://github.com/anilcancakir/laravel-ai-sdk-skills/pull/3
* Per-skill inclusion mode + simpler skill injection by @GregPeden in https://github.com/anilcancakir/laravel-ai-sdk-skills/pull/4
### New Contributors

* @GregPeden made their first contribution in https://github.com/anilcancakir/laravel-ai-sdk-skills/pull/2

**Full Changelog**: https://github.com/anilcancakir/laravel-ai-sdk-skills/compare/v1.0.0...v1.0.1

## [v1.0.0](https://github.com/anilcancakir/laravel-ai-sdk-skills/releases/tag/v1.0.0) - 2026-02-11

### Added
- **Skillable Trait**: Unified trait for agent-specific skill loading (`skills`, `skillTools`, `skillInstructions`).
- **Discovery System**: Local skill discovery with configurable paths and cache settings.
- **Meta-Tools**:
  - `list_skills`: Discover available capabilities.
  - `skill`: Dynamic loading of skill instructions and tools (via `SkillLoader`).
  - `skill_read`: Safely read supplementary files within a skill's directory.
- **Skill Parser**: YAML frontmatter support for metadata (`name`, `description`) with markdown body extraction for instructions.
- **Artisan Commands**:
  - `skills:list`: Display discovered skills.
  - `skills:make {name}`: Generate skill scaffolds.
  - `skills:clear`: Flush discovery cache.
- **Configuration**: Full environment variable support for all settings (`SKILLS_ENABLED`, `SKILLS_DISCOVERY_MODE`, etc.).
