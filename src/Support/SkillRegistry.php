<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;

/**
 * Manages the registration and loading of AI skills.
 */
class SkillRegistry
{
    /**
     * The list of loaded skills.
     *
     * @var array<string, Skill>
     */
    protected array $loaded = [];

    /**
     * Create a new skill registry instance.
     *
     * @param  SkillDiscovery  $discovery  The skill discovery instance.
     * @return void
     */
    public function __construct(
        protected SkillDiscovery $discovery,
    ) {}

    /**
     * Load a skill by name or path.
     *
     * @param  string  $nameOrPath  The name or path of the skill to load.
     * @return Skill|null The loaded skill or null on failure.
     */
    public function load(string $nameOrPath): ?Skill
    {
        $skill = $this->discovery->resolve($nameOrPath);

        if ($skill) {
            $this->loaded[$skill->slug()] = $skill;
        }

        return $skill;
    }

    /**
     * Determine if a skill is loaded by name.
     *
     * @param  string  $name  The name of the skill.
     */
    public function isLoaded(string $name): bool
    {
        return isset($this->loaded[$name]);
    }

    /**
     * Get a loaded skill by name.
     *
     * @param  string  $name  The name of the skill.
     */
    public function get(string $name): ?Skill
    {
        return $this->loaded[$name] ?? null;
    }

    /**
     * Get all available skills.
     *
     * @return Collection<string, Skill>
     */
    public function available(): Collection
    {
        return $this->discovery->discover();
    }

    /**
     * Get all loaded skills.
     *
     * @return array<string, Skill>
     */
    public function getLoaded(): array
    {
        return $this->loaded;
    }

    /**
     * Get all tools from loaded skills.
     *
     * @return array<int, Tool>
     */
    public function tools(): array
    {
        $tools = [];

        foreach ($this->loaded as $skill) {
            foreach ($skill->tools as $toolClass) {
                if (class_exists($toolClass)) {
                    $tools[] = app($toolClass);
                } else {
                    Log::warning("Tool class [{$toolClass}] not found for skill [{$skill->name}].");
                }
            }
        }

        return $tools;
    }

    /**
     * Get the combined instructions from all loaded skills.
     *
     * In 'lite' mode, only skill name and description are returned.
     * In 'full' mode, full instructions are included.
     */
    public function instructions(?string $mode = null): string
    {
        $mode ??= config('skills.discovery_mode', 'lite');

        $instructions = '';

        foreach ($this->loaded as $skill) {
            if ($mode === 'full') {
                $instructions .= sprintf(
                    "<skill name=\"%s\">\n%s\n</skill>\n",
                    $skill->name,
                    trim($skill->instructions)
                );
            } else {
                $instructions .= sprintf(
                    "<skill name=\"%s\" description=\"%s\" />\n",
                    $skill->name,
                    $skill->description
                );
            }
        }

        return trim($instructions);
    }
}
