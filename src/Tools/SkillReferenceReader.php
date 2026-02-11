<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tools;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SkillReferenceReader implements Tool
{
    public function __construct(
        protected SkillRegistry $registry,
    ) {}

    public function name(): string
    {
        return 'skill_read';
    }

    public function description(): Stringable|string
    {
        return 'Read a reference file from a loaded skill\'s directory. '
            .'Requires BOTH parameters: "skill" (the skill slug, e.g. "wind-ui") '
            .'AND "file" (the relative path, e.g. "references/utilities.md"). '
            .'The skill must be loaded first via the "skill" tool. '
            .'Available reference files are listed when a skill is loaded.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'skill' => $schema->string()->description('REQUIRED. The name/slug of the already-loaded skill (e.g. "wind-ui", "git-helper"). Must match the skill name used when loading.')->required(),
            'file' => $schema->string()->description('REQUIRED. Relative file path within the skill directory (e.g. "references/utilities.md", "references/theme.md"). See the file list from the skill loading response.')->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $skillName = $request->string('skill')->value();
        $filePath = $request->string('file')->value();

        if (empty($skillName) || empty($filePath)) {
            return 'Error: Both "skill" and "file" parameters are required.';
        }

        $skill = $this->registry->get($skillName);

        if ($skill === null) {
            return "Error: Skill [{$skillName}] is not loaded. Load it first using the \"skill\" tool.";
        }

        if ($skill->basePath === null) {
            return "Error: Skill [{$skillName}] has no base path (remote skills do not support file references).";
        }

        $resolvedPath = $this->resolveSecurePath($skill->basePath, $filePath);

        if ($resolvedPath === null) {
            return "Error: Access denied. The file path \"{$filePath}\" escapes the skill's directory boundary.";
        }

        if (! file_exists($resolvedPath) || ! is_file($resolvedPath)) {
            return "Error: File \"{$filePath}\" not found in skill [{$skillName}].";
        }

        $content = file_get_contents($resolvedPath);

        if ($content === false) {
            return "Error: Unable to read file \"{$filePath}\" from skill [{$skillName}].";
        }

        return sprintf(
            "<skill_reference skill=\"%s\" file=\"%s\">\n%s\n</skill_reference>",
            $skill->name,
            $filePath,
            trim($content)
        );
    }

    /**
     * Resolve a relative file path securely within a skill's base directory.
     *
     * Prevents directory traversal attacks by:
     * 1. Blocking path components that attempt traversal (../)
     * 2. Resolving the real filesystem path to catch symlink escapes
     * 3. Verifying the resolved path starts with the skill's real base path
     */
    protected function resolveSecurePath(string $basePath, string $filePath): ?string
    {
        // Block obvious traversal patterns before any filesystem interaction
        $normalized = str_replace('\\', '/', $filePath);

        if (str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
            return null;
        }

        // Resolve the real base path (follow symlinks at the base level)
        $realBasePath = realpath($basePath);

        if ($realBasePath === false) {
            return null;
        }

        $candidatePath = $realBasePath.DIRECTORY_SEPARATOR.$filePath;

        // If file exists, resolve its real path and verify containment
        if (file_exists($candidatePath)) {
            $realCandidatePath = realpath($candidatePath);

            if ($realCandidatePath === false) {
                return null;
            }

            // The resolved path MUST start with the skill's real base directory
            if (! str_starts_with($realCandidatePath, $realBasePath.DIRECTORY_SEPARATOR)) {
                return null;
            }

            return $realCandidatePath;
        }

        // File doesn't exist — return the candidate so handle() produces "not found"
        // but only if it looks structurally safe (no traversal in normalized form)
        return $candidatePath;
    }
}
