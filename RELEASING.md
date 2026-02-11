# Releasing

This document describes the release process for `anilcancakir/laravel-ai-sdk-skills`.

## How It Works

```
git tag → push → GitHub Action → GitHub Release + CHANGELOG.md update → Packagist webhook sync
```

1. You push a semantic version tag (e.g., `v1.2.0`)
2. GitHub Actions automatically creates a GitHub Release with auto-generated notes
3. The workflow updates `CHANGELOG.md` and commits it back to the default branch
4. Packagist webhook detects the push and syncs the new version

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
- Packagist syncs automatically via webhook

## Version Guidelines

Follow [Semantic Versioning](https://semver.org/):

| Change Type | Version Bump | Example |
|-------------|-------------|---------|
| Bug fix | Patch | `v1.0.0` → `v1.0.1` |
| New feature (backwards compatible) | Minor | `v1.0.0` → `v1.1.0` |
| Breaking change | Major | `v1.0.0` → `v2.0.0` |

## Release Notes Categories

Pull requests are automatically categorized in release notes based on labels:

| Label | Category |
|-------|----------|
| `enhancement`, `feature` | 🚀 Features |
| `bug`, `fix` | 🐛 Bug Fixes |
| `documentation` | 📖 Documentation |
| `chore`, `maintenance`, `dependencies` | 🔧 Maintenance |
| `breaking-change` | 💥 Breaking Changes |

Apply labels to your PRs for well-organized release notes.

---

## One-Time Setup: Packagist

### 1. Register the Package

1. Go to [packagist.org/packages/submit](https://packagist.org/packages/submit)
2. Log in with your GitHub account
3. Enter the repository URL: `https://github.com/anilcancakir/laravel-ai-sdk-skills`
4. Submit — Packagist will index the existing `v1.0.0` tag

### 2. Configure the Webhook

1. Get your Packagist API token:
   - Go to [packagist.org/profile](https://packagist.org/profile)
   - Click **Show API Token** and copy it

2. Add the webhook to GitHub:
   - Go to **Repository Settings** → **Webhooks** → **Add webhook**
   - **Payload URL**: `https://packagist.org/api/github?username=YOUR_PACKAGIST_USERNAME`
   - **Content type**: `application/json`
   - **Secret**: Paste your Packagist API token
   - **Events**: Select **Just the push event**
   - Click **Add webhook**

3. Verify:
   - Push any commit or tag
   - Check the webhook delivery in GitHub (Settings → Webhooks → Recent Deliveries)
   - Confirm the package updates on Packagist

> **Note**: The webhook handles all future updates. Every `git push` (including tags) will notify Packagist to re-index.
