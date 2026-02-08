<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Console;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillDiscovery;
use Illuminate\Console\Command;

/**
 * Command to clear the AI skills cache.
 */
class SkillsClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'skills:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the AI skills cache';

    /**
     * Execute the console command.
     *
     * @param  SkillDiscovery  $discovery  The skill discovery instance.
     */
    public function handle(SkillDiscovery $discovery): int
    {
        $discovery->clearCache();

        $this->info('Skills cache cleared successfully.');

        return self::SUCCESS;
    }
}
