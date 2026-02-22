# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.0.1](https://github.com/anilcancakir/laravel-ai-sdk-skills/compare/v1.0.0...v1.0.1) - 2026-02-22

### Added

- **Per-Skill Inclusion Modes**: Override the global `discovery_mode` on a per-skill basis using keyed arrays (`'skill-name' => SkillInclusionMode::Full`). Supports enum values, canonical strings (`lite`, `full`), and aliases (`lazy`, `eager`).
- **`withSkillInstructions()` Helper**: Compose prompt-caching-friendly system instructions with static content first, skill instructions in the middle, and dynamic content last. Both parameters are optional.
- **Cache Configuration**: New `cache.enabled` and `cache.store` config keys with environment variable support (`SKILLS_CACHE_ENABLED`, `SKILLS_CACHE_STORE`).
- **Symfony YAML v8 Support**: Permit `symfony/yaml` v7.x or v8.x.
- **Backward Compatibility Test Suite**: Dedicated BC tests covering all v1.0.0 usage patterns.

### Fixed

- **Per-Skill Mode Persistence**: `SkillRegistry::load()` no longer overwrites explicit mode preferences when a skill is re-loaded without a mode argument (e.g. via `SkillLoader` tool re-scan).
- **`withSkillInstructions()` Signature**: Both `$staticPrompt` and `$dynamicPrompt` are now optional, preserving backward compatibility with existing agent implementations.

### Changed

- **README**: Restored simple Quick Start example, moved advanced features (per-skill modes, prompt caching) to a dedicated Advanced Usage section.

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
