<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Command to create a new AI skill.
 */
class SkillsMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'skills:make {name : The name of the skill} {--description= : The description of the skill}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new AI skill';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $description = $this->option('description') ?? 'A new AI skill';

        $paths = config('skills.paths');
        $basePath = ! empty($paths) ? $paths[0] : resource_path('skills');

        $skillDirectory = $basePath.'/'.$name;
        $skillFile = $skillDirectory.'/SKILL.md';

        if (File::exists($skillFile)) {
            $this->error("Skill [{$name}] already exists.");

            return self::FAILURE;
        }

        if (! File::isDirectory($skillDirectory)) {
            File::makeDirectory($skillDirectory, 0755, true);
        }

        $stub = File::exists(__DIR__.'/../../stubs/skill.stub')
            ? File::get(__DIR__.'/../../stubs/skill.stub')
            : $this->defaultStub();

        $content = str_replace(
            ['{{ name }}', '{{ description }}', '{{ tool_name }}'],
            [$name, $description, Str::kebab($name)],
            $stub
        );

        File::put($skillFile, $content);

        $this->info("Skill [{$name}] created successfully.");

        return self::SUCCESS;
    }

    /**
     * Get the default skill stub.
     */
    protected function defaultStub(): string
    {
        return <<<'EOT'
---
name: {{ name }}
description: {{ description }}
---
# {{ name }}

This is a new skill. Describe what it does here.
EOT;
    }
}
