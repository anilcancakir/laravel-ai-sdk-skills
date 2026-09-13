# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.2.0](https://github.com/anilcancakir/laravel-ai-sdk-skills/releases/tag/v1.2.0) - 2026-09-13

### Added
- **Path containment on skill loading** (always on): when the model names a directory rather than a slug, that directory must now resolve inside one of the configured `skills.paths` roots. A root whose `realpath()` fails is skipped, and a directory-shaped request is refused when no configured root survives, which is the state of a fresh install where the default `resource_path('skills')` does not exist yet. A path declared by a developer in an agent's own `skills()` method is unaffected; this applies to what the model supplies.
- **Load-time skill authorization** (opt in, off by default): the new `skills.enforce_declared` option restricts an agent to the skills it declares in its own `skills()` method. It covers `skill`, `list_skills`, `skill_read`, `tools()` and `instructions()`, so an undeclared skill is neither listed, loaded, nor readable by reference. The allowlist is built from each declared entry's resolved slug, because a declaration may be a slug, a display name or a path while the model always asks by slug.
- A refused load returns the same "not found" text as a genuine miss, so an agent cannot enumerate the skills directory by probing names. The real reason is written to the log instead.

### Changed
- `laravel/ai` is now a runtime requirement at `>=0.7 <0.12` (previously `require-dev` at `^0.1.3`), so installing this package delivers the library it is built against.
- PHP minimum raised to 8.3 (from 8.2). Laravel minimum raised to 12 (from 11), so Laravel 11 is no longer supported.
- `minimum-stability: dev` removed from `composer.json`; the package now resolves under the default stable policy.
- CI matrix expanded to PHP 8.3, 8.4 and 8.5 against Laravel 12 and 13, plus a job that pins `laravel/ai` to the bottom of the supported range and asserts what actually resolved.
- Test suite now runs against the real `Laravel\Ai\Contracts\Tool` contract; the local stubs under `tests/Stubs/` are gone.
- **Tool names are unchanged.** `list_skills`, `skill` and `skill_read` are exactly as before, and so are the class names `ListSkills`, `SkillLoader` and `SkillReferenceReader`.

### Fixed
- **Published configuration crash** (affects v1.0.1 through v1.1.0, unrelated to the dependency work above): `config/skills.php` called `app()->environment()` while computing `cache.enabled`. Laravel reads published config during the `LoadConfiguration` bootstrap, before the `env` container binding exists, so `php artisan vendor:publish --provider="AnilcanCakir\LaravelAiSdkSkills\SkillsServiceProvider"`, which this README tells you to run, left every subsequent artisan command dying with `Class "env" does not exist`. The failure is total rather than degraded, and it only appears once the config is published, which is why the test suite never saw it. Replaced with an `env('APP_ENV')` comparison that touches no container binding. If you are on v1.0.1 or later and have published the config, this is the fix you want regardless of whether you were waiting on the `laravel/ai` realignment.
- **Skill loaded but reported missing**: `SkillRegistry::load()` stores a skill under its resolved slug while `get()` looked it up by the raw argument, so passing a path to the `skill` tool returned "not found" while the skill sat loaded in the registry and remained readable through `skill_read`. The tool now renders from what `load()` returns.

### Notes
- **Non-breaking at the API level, but upgrading has requirements.** Every published API and tool name is unchanged and nothing you call needs editing. Three things are not "no change at all", stated plainly so you can check them before you upgrade:
  - You need PHP 8.3 and Laravel 12 or 13. On PHP 8.2 or Laravel 11, Composer will simply never offer you this release.
  - Path containment applies whether or not you enable `enforce_declared`. It affects only a directory path the model names, not a path you write into your agent's own `skills()` method.
  - `SkillRegistry::tools()` and `::instructions()` each gained an optional trailing parameter. Calling them is unaffected, but a subclass that overrides either with the old signature will fatal on the signature mismatch.

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
