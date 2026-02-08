<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Support;

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
     * @param  array  $triggers  The list of keywords that trigger this skill.
     * @return void
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $instructions,
        public array $tools,
        public array $triggers,
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
     * Determine if the skill matches the given input trigger.
     *
     * @param  string  $input  The user input to check against triggers.
     */
    public function matchesTrigger(string $input): bool
    {
        if (empty($this->triggers)) {
            return false;
        }

        return Str::contains(
            Str::lower($input),
            array_map(fn ($trigger) => Str::lower($trigger), $this->triggers)
        );
    }
}
