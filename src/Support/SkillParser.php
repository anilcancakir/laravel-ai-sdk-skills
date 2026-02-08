<?php

declare(strict_types=1);

namespace AnilcanCakir\LaravelAiSdkSkills\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parse skill definitions from markdown files with YAML frontmatter.
 */
class SkillParser
{
    /**
     * Parse the given markdown content into a Skill instance.
     *
     * @param  string  $content  The markdown content to parse.
     * @return Skill|null The parsed skill or null on failure.
     */
    public static function parse(string $content): ?Skill
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        if (! str_starts_with($content, "---\n")) {
            Log::warning('SkillParser: Missing opening frontmatter delimiter.');

            return null;
        }

        $parts = explode("\n---\n", $content, 2);

        if (count($parts) < 2) {
            Log::warning('SkillParser: Missing closing frontmatter delimiter or body.');

            return null;
        }

        $parts[0] = str_replace("\n...", '', $parts[0]);

        $frontmatter = substr($parts[0], 4);
        $body = trim($parts[1]);

        try {
            $data = Yaml::parse($frontmatter);
        } catch (ParseException $e) {
            Log::warning('SkillParser: YAML parse error: '.$e->getMessage());

            return null;
        }

        if (! is_array($data)) {
            Log::warning('SkillParser: Invalid frontmatter format.');

            return null;
        }

        if (empty($data['name']) || ! is_string($data['name'])) {
            Log::warning("SkillParser: Missing or invalid 'name' field.");

            return null;
        }

        if (empty($data['description']) || ! is_string($data['description'])) {
            Log::warning("SkillParser: Missing or invalid 'description' field.");

            return null;
        }

        $tools = $data['tools'] ?? [];
        if (! is_array($tools)) {
            Log::warning("SkillParser: 'tools' must be an array.");

            return null;
        }

        $triggers = $data['triggers'] ?? [];
        if (! is_array($triggers)) {
            Log::warning("SkillParser: 'triggers' must be an array.");

            return null;
        }

        return new Skill(
            name: $data['name'],
            description: $data['description'],
            instructions: $body,
            tools: $tools,
            triggers: $triggers,
        );
    }
}
