# Releasing

This document describes the release process for `anilcancakir/laravel-ai-sdk-skills`.

## How It Works

```
git tag → push → GitHub Action → GitHub Release + CHANGELOG.md update → Packagist Update
```

1. You push a semantic version tag (e.g., `v1.2.0`)
2. GitHub Actions automatically creates a GitHub Release with auto-generated notes
3. The workflow updates `CHANGELOG.md` and commits it back to the default branch
4. **The workflow triggers Packagist update via API**

## Creating a Release

### 1. Prepare

Ensure all changes are merged to `main` and CI is green.

### 2. Tag and Push

```bash
git tag v1.2.0
git push origin v1.2.0
```

### 3. Done

The GitHub Action handles everything else:
- Creates a GitHub Release at [Releases](https://github.com/anilcancakir/laravel-ai-sdk-skills/releases)
- Updates `CHANGELOG.md` with categorized release notes
- **Syncs with Packagist automatically**

## One-Time Setup: Secrets

For the automatic Packagist update to work, you must add the following **Repository Secrets**:

1. Go to **Settings** → **Secrets and variables** → **Actions** → **New repository secret**
2. Add:
   - `PACKAGIST_USERNAME`: Your Packagist username (e.g., `anilcancakir`)
   - `PACKAGIST_API_TOKEN`: Your **Safe API Token** (recommended) or Main API Token from [packagist.org/profile](https://packagist.org/profile)

## Version Guidelines

Follow [Semantic Versioning](https://semver.org/):

| Change Type | Version Bump | Example |
|-------------|-------------|---------|
| Bug fix | Patch | `v1.0.0` → `v1.0.1` |
| New feature (backwards compatible) | Minor | `v1.0.0` → `v1.1.0` |
| Breaking change | Major | `v1.0.0` → `v2.0.0` |
