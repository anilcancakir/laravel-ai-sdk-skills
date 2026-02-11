<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Traits;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tools\ListSkills;
use AnilcanCakir\LaravelAiSdkSkills\Tools\SkillLoader;
use AnilcanCakir\LaravelAiSdkSkills\Tools\SkillReferenceReader;
use Laravel\Ai\Contracts\Tool;

use function app;

/**
 * Trait to add skill capabilities to AI agents.
 *
 * This trait provides a simple way to integrate the skill system into your agents.
 * Skills are loaded lazily on first access to skillTools() or skillInstructions().
 *
 * Basic usage - just add the trait and define skills():
 *
 *     class MyAgent implements Agent, HasTools
 *     {
 *         use Skillable;
 *
 *         public function skills(): iterable
 *         {
 *             return ['my-skill'];
 *         }
 *
 *         public function tools(): iterable
 *         {
 *             return $this->skillTools();
 *         }
 *     }
 */
trait Skillable
{
    /**
     * Whether skills have been booted for this instance.
     */
    private bool $skillsBooted = false;

    /**
     * Get the list of skills to be loaded.
     *
     * Override this method to define which skills your agent uses.
     *
     * @return iterable<int, string>
     */
    public function skills(): iterable
    {
        return [];
    }

    /**
     * Get skill tools including meta-tools (ListSkills, SkillLoader) and loaded skill tools.
     *
     * This method lazily boots skills on first call.
     *
     * @return array<int, Tool>
     */
    public function skillTools(): array
    {
        if (! config('skills.enabled', true)) {
            return [];
        }

        $this->bootSkillsIfNeeded();

        $registry = app(SkillRegistry::class);

        return array_merge(
            [
                new ListSkills($registry),
                new SkillLoader($registry),
                new SkillReferenceReader($registry),
            ],
            $registry->tools()
        );
    }

    /**
     * Get the combined instructions from all loaded skills.
     *
     * This is optional - only call it if you want to inject skill instructions
     * into your agent's instructions. If you don't call this, skills still work
     * via their tools.
     *
     * @param  string|null  $mode  Override the discovery mode ('lite' or 'full'). Defaults to config value.
     */
    public function skillInstructions(?string $mode = null): string
    {
        if (! config('skills.enabled', true)) {
            return '';
        }

        $this->bootSkillsIfNeeded();

        return app(SkillRegistry::class)->instructions($mode);
    }

    /**
     * Boot skills if not already booted.
     */
    private function bootSkillsIfNeeded(): void
    {
        if ($this->skillsBooted || ! config('skills.enabled', true)) {
            return;
        }

        $this->skillsBooted = true;

        foreach ($this->skills() as $skillNameOrPath) {
            app(SkillRegistry::class)->load($skillNameOrPath);
        }
    }
}
