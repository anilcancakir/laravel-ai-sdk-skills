# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
