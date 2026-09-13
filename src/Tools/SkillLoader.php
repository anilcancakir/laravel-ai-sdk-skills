<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tools;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Tool to load a specific AI skill.
 */
class SkillLoader implements Tool
{
    /**
     * Create a new skill loader tool instance.
     *
     * @param  SkillRegistry  $registry  The skill registry instance.
     * @param  array<int, string>|null  $declaredSlugs  Slugs the owning agent declared; null defers to the registry.
     * @return void
     */
    public function __construct(
        protected SkillRegistry $registry,
        protected ?array $declaredSlugs = null,
    ) {}

    /**
     * Get the tool's name.
     */
    public function name(): string
    {
        return 'skill';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Load a specific skill by its name (slug) to gain its capabilities and instructions.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The unique name/slug of the skill to load (e.g. "doc-writer", "git-helper")')->required(),
        ];
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $name = $request->string('name')->value();

        if (empty($name)) {
            return 'Error: Skill name is required.';
        }

        // Refusals answer with the plain "not found" string so the model cannot use the
        // difference between a refusal and a miss to enumerate skills or directories.
        if (! $this->withinConfiguredPaths($name)) {
            Log::warning("Skill path [{$name}] resolves outside every configured skills path. Refusing to load it.");

            return "Skill [{$name}] not found.";
        }

        if (config('skills.enforce_declared', false) && ! $this->registry->declares($name, $this->declaredSlugs)) {
            Log::warning("Skill [{$name}] was not declared by the calling agent. Refusing to load it.");

            return "Skill [{$name}] not found.";
        }

        if ($skill = $this->registry->load($name)) {
            $output = sprintf(
                "<skill name=\"%s\">\n%s\n</skill>",
                $skill->name,
                trim($skill->instructions)
            );

            $referenceFiles = $skill->referenceFiles();

            if (! empty($referenceFiles)) {
                $fileList = implode("\n", array_map(
                    fn (string $file) => "  - {$file}",
                    $referenceFiles
                ));

                $exampleFile = $referenceFiles[0];

                $output .= "\n\n<skill_references skill=\"{$skill->name}\">\n"
                    ."Available reference files (use `skill_read` tool to read them):\n"
                    ."{$fileList}\n\n"
                    ."To read a reference file, call skill_read with BOTH required parameters:\n"
                    ."  skill: \"{$skill->name}\"\n"
                    ."  file: \"{$exampleFile}\"\n"
                    .'</skill_references>';
            }

            return $output;
        }

        return "Skill [{$name}] not found.";
    }

    /**
     * Determine if a model-supplied skill directory sits inside a configured skills path.
     *
     * The model may only reach the filesystem through this tool, so a directory argument is
     * contained here:
     * 1. Only an existing directory is checked; a slug is resolved by discovery, which scans
     *    the configured paths already.
     * 2. Both sides are compared on their real path, so a symlink or a "../" segment cannot
     *    point out of a configured root.
     * 3. A root whose realpath() fails is skipped rather than compared. False concatenated
     *    with the separator is "/", which prefixes every absolute path, so keeping it would
     *    turn this check into a total bypass on a fresh install where no root exists yet.
     * 4. The separator is appended before the prefix test, so a root does not admit a sibling
     *    that merely starts with its name (/a/skills must not admit /a/skills-evil).
     */
    protected function withinConfiguredPaths(string $name): bool
    {
        if (! is_dir($name)) {
            return true;
        }

        $realPath = realpath($name);

        if ($realPath === false) {
            return false;
        }

        foreach ((array) config('skills.paths', []) as $path) {
            // An empty entry would admit everything: realpath('') returns the working
            // directory, so a single blank string in skills.paths makes the whole
            // project tree a configured root.
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $realRoot = realpath($path);

            if ($realRoot === false) {
                continue;
            }

            if ($realPath === $realRoot || str_starts_with($realPath, $realRoot.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }
}
