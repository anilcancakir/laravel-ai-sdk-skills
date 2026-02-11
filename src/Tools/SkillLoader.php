<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tools;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
     * @return void
     */
    public function __construct(
        protected SkillRegistry $registry,
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

        $this->registry->load($name);

        if ($skill = $this->registry->get($name)) {
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
}
