<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tools;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Tool to list all available AI skills.
 */
class ListSkills implements Tool
{
    /**
     * Create a new list skills tool instance.
     *
     * @param  SkillRegistry  $registry  The skill registry instance.
     * @param  int  $maxDescriptionSkills  Maximum skills to include in description.
     * @param  string|null  $mode  The discovery mode ('lite' or 'full').
     * @return void
     */
    public function __construct(
        protected SkillRegistry $registry,
        protected int $maxDescriptionSkills = 50,
        protected ?string $mode = null,
    ) {}

    /**
     * Get the tool's name.
     */
    public function name(): string
    {
        return 'list_skills';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        $baseDescription = 'List all available skills that can be loaded to provide specialized capabilities. Returns a table of skills with their descriptions, triggers, and current status.';

        $availableSkills = $this->registry->available();

        $skillsXml = $this->buildSkillsXml($availableSkills->take($this->maxDescriptionSkills));

        return $baseDescription."\n\n".$skillsXml;
    }

    /**
     * Build XML representation of available skills.
     *
     * @param  \Illuminate\Support\Collection  $skills  The skills to include.
     * @return string The XML string.
     */
    protected function buildSkillsXml($skills): string
    {
        $mode = $this->mode ?? config('skills.discovery_mode', 'lite');
        $xml = "<available_skills>\n";

        foreach ($skills as $skill) {
            $xml .= "  <skill>\n";
            $xml .= "    <name>{$skill->name}</name>\n";
            $xml .= "    <description>{$skill->description}</description>\n";

            if ($mode === 'full') {
                $xml .= "    <instructions>{$skill->instructions}</instructions>\n";
            }

            $xml .= "  </skill>\n";
        }

        $xml .= '</available_skills>';

        return $xml;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()->description('Optional keyword to filter skills by name or description'),
        ];
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $available = $this->registry->available();
        $loaded = $this->registry->getLoaded();
        $filter = $request->string('filter')->value();

        $rows = [];

        foreach ($available as $slug => $skill) {
            if (! empty($filter) && ! str_contains(strtolower($skill->name.$skill->description), strtolower($filter))) {
                continue;
            }

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

        if (empty($rows)) {
            return 'No skills found'.($filter ? " matching filter '{$filter}'" : '').'.';
        }

        return implode("\n", [$header, $divider, ...$rows]);
    }
}
