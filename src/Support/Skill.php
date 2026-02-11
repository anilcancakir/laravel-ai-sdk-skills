<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Represents a specialized AI skill.
 */
readonly class Skill
{
    /**
     * Create a new skill instance.
     *
     * @param  string  $name  The name of the skill.
     * @param  string  $description  The description of the skill.
     * @param  string  $instructions  The instructions for the AI to follow.
     * @param  array  $tools  The list of tools available for this skill.
     * @param  string|null  $basePath  The absolute path to the skill's directory.
     * @return void
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $instructions,
        public array $tools,
        public ?string $basePath = null,
    ) {}

    /**
     * Get the slugified name of the skill.
     */
    public function slug(): string
    {
        return Str::slug($this->name);
    }

    /**
     * Determine if the skill has any tools.
     */
    public function hasTools(): bool
    {
        return count($this->tools) > 0;
    }

    /**
     * Determine if the skill has reference files available.
     */
    public function hasReferenceFiles(): bool
    {
        return count($this->referenceFiles()) > 0;
    }

    /**
     * Get the list of reference files available in the skill's directory.
     *
     * Scans for all non-SKILL.md files (markdown, text, yaml, json)
     * within the skill's base directory recursively.
     *
     * @return array<int, string> Relative file paths.
     */
    public function referenceFiles(): array
    {
        if ($this->basePath === null || ! is_dir($this->basePath)) {
            return [];
        }

        $filesystem = new Filesystem;
        $allowedExtensions = ['md', 'txt', 'yaml', 'yml', 'json'];
        $files = [];

        foreach ($filesystem->allFiles($this->basePath) as $file) {
            if (! in_array(strtolower($file->getExtension()), $allowedExtensions, true)) {
                continue;
            }

            $relativePath = $file->getRelativePathname();

            if ($relativePath === 'SKILL.md') {
                continue;
            }

            $files[] = $relativePath;
        }

        sort($files);

        return $files;
    }
}
