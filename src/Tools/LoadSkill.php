<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tools;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use Laravel\Ai\Contracts\Tool;

/**
 * Tool to load a specialized skill.
 */
class LoadSkill implements Tool
{
    /**
     * Create a new load skill tool instance.
     *
     * @param  SkillRegistry  $registry  The skill registry instance.
     * @return void
     */
    public function __construct(
        protected SkillRegistry $registry,
    ) {}

    /**
     * Get the name of the tool.
     */
    public function name(): string
    {
        return 'load_skill';
    }

    /**
     * Get the description of the tool.
     */
    public function description(): string
    {
        return 'Load a specialized skill to get detailed instructions and tools for a specific task.';
    }

    /**
     * Get the JSON schema for the tool's parameters.
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'The name of the skill to load (e.g. "git-master", "frontend-ui-ux")',
                ],
            ],
            'required' => ['name'],
        ];
    }

    /**
     * Handle the tool execution.
     *
     * @param  array<string, mixed>  $arguments  The tool arguments.
     */
    public function handle(array $arguments): string
    {
        $name = $arguments['name'] ?? '';

        $this->registry->load($name);

        if ($skill = $this->registry->get($name)) {
            return sprintf(
                "Skill [%s] loaded.\n\nInstructions:\n%s",
                $skill->name,
                $skill->instructions
            );
        }

        return "Skill [{$name}] not found.";
    }
}
