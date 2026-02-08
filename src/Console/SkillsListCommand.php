<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Console;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use Illuminate\Console\Command;

/**
 * Command to list all available AI skills.
 */
class SkillsListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'skills:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all available AI skills';

    /**
     * Execute the console command.
     *
     * @param  SkillRegistry  $registry  The skill registry instance.
     */
    public function handle(SkillRegistry $registry): int
    {
        $skills = $registry->available();

        if ($skills->isEmpty()) {
            $this->info('No skills found.');

            return self::SUCCESS;
        }

        $rows = $skills->map(fn ($skill) => [
            $skill->name,
            $skill->description,
            implode(', ', $skill->tools ?? []),
        ]);

        $this->table(
            ['Name', 'Description', 'Tools'],
            $rows
        );

        return self::SUCCESS;
    }
}
