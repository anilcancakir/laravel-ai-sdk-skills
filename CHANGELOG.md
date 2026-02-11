# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **`skill_read` Tool**: New meta-tool allowing agents to read supplementary files within a skill's directory (e.g., `references/utilities.md`) with directory traversal protection
- **Remote Discovery**: Skills can now be fetched from a remote API endpoint (`remote` and `dual` modes)
- **Dual-Mode Discovery**: Merge skills from both local filesystem and remote sources simultaneously
- **Skill Source Mode**: New `mode` config key (`local`, `remote`, `dual`) to control where skills are discovered from
- **Extended Skill Metadata**: `mcp`, and `constraints` fields in YAML frontmatter
- **Environment Configuration**: All config values now support `env()` overrides (`SKILLS_ENABLED`, `SKILLS_DISCOVERY_MODE`, `SKILLS_MODE`, `SKILLS_REMOTE_URL`, `SKILLS_REMOTE_TOKEN`, `SKILLS_REMOTE_TIMEOUT`)
- **Composer Scripts**: `test`, `lint`, and `format` scripts for development workflow

### Changed

- **Renamed `LoadSkill` to `SkillLoader`**: Tool class renamed for consistency (tool name remains `skill`)
- **`ListSkills` tool**: Added `name()` method for explicit tool naming
- **Config enabled flag**: `skills.enabled` config key to globally enable/disable the skill system
- **Skill stub**: Updated template with `tools` array in YAML frontmatter

### Fixed

- **Tool interface compatibility**: Fixed `name()` method usage.
- **Test alignment**: Updated tests to reflect metadata changes.
- **`skill_read` Tool**: Enhanced parameter descriptions to ensure correct LLM usage.

## [1.0.0] - 2026-02-09

### Added

- **Skillable Trait**: New unified trait for agent-specific skill loading
  - Automatic skill loading on agent instantiation via `bootSkillable()`
  - Support for loading skills by name or direct path
  - `skillTools()` method returns meta-tools and loaded skill tools
  - `skillInstructions()` method returns combined skill instructions

- **Skill Discovery System**
  - `SkillDiscovery` class for automatic skill detection in configured paths
  - `resolve()` method supports both name-based and path-based skill resolution
  - Cache support with configurable TTL

- **Skill Registry**
  - Request-scoped singleton for managing loaded skills
  - `load()`, `isLoaded()`, `get()`, and `available()` methods
  - Tool instantiation with dependency injection support

- **Skill Parser**
  - YAML frontmatter parsing for skill metadata
  - Markdown body extraction for instructions
  - Support for `name`, `description`, `version`, `triggers`, and `tools` fields

- **Meta-Tools**
  - `ListSkills`: Allows agents to discover available skills at runtime
  - `LoadSkill`: Allows agents to dynamically load skills during execution

- **Artisan Commands**
  - `skills:list`: Display all discovered skills with metadata
  - `skills:make {name}`: Generate a new skill scaffold
  - `skills:clear`: Clear the skill discovery cache

- **Configuration**
  - `config/skills.php` with customizable paths and cache settings
  - Default skill path: `resources/skills`

### Removed

- **HasSkills Trait**: Merged into `Skillable` trait for unified API

### Changed

- Default skill directory changed from `app/Skills` to `resources/skills`

[Unreleased]: https://github.com/anilcancakir/laravel-ai-sdk-skills/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/anilcancakir/laravel-ai-sdk-skills/releases/tag/v1.0.0
