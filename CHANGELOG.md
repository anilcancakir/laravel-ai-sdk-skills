# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
