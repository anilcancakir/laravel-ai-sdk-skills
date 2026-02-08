<?php

declare(strict_types=1);

namespace AnilcanCakir\LaravelAiSdkSkills\Tools;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Tool to list all available AI skills.
 */
class ListSkills implements Tool
{
    /**
     * Create a new list skills tool instance.
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
        return 'list_skills';
    }

    /**
     * Get the description of the tool.
     */
    public function description(): string
    {
        return 'List all available skills that can be loaded to provide specialized capabilities. Returns a table of skills with their descriptions, triggers, and current status.';
    }

    /**
     * Get the JSON schema for the tool's parameters.
     *
     * @param  JsonSchema  $schema  The schema instance.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Handle the tool execution.
     *
     * @param  Request  $request  The tool request instance.
     */
    public function handle(Request $request): string
    {
        $available = $this->registry->available();
        $loaded = $this->registry->getLoaded();

        $rows = [];

        foreach ($available as $slug => $skill) {
            $status = isset($loaded[$slug]) ? 'Loaded' : 'Available';
            $triggers = implode(', ', $skill->triggers);

            // Sanitize pipe characters in content to prevent breaking markdown table
            $name = str_replace('|', '\|', $skill->name);
            $desc = str_replace('|', '\|', $skill->description);
            $triggers = str_replace('|', '\|', $triggers);

            $rows[] = "| {$name} | {$desc} | {$triggers} | {$status} |";
        }

        $header = '| Name | Description | Triggers | Status |';
        $divider = '|---|---|---|---|';

        return implode("\n", [$header, $divider, ...$rows]);
    }
}
