<?php

declare(strict_types=1);

namespace AnilcanCakir\LaravelAiSdkSkills\Traits;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tools\ListSkills;
use AnilcanCakir\LaravelAiSdkSkills\Tools\LoadSkill;
use Laravel\Ai\Contracts\Tool;

use function app;

/**
 * Trait to make an object capable of using AI skills.
 */
trait Skillable
{
    /**
     * Create a new skillable instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->bootSkillable();
    }

    /**
     * Boot the skillable trait and load configured skills.
     */
    public function bootSkillable(): void
    {
        if (method_exists($this, 'skills')) {
            foreach ($this->skills() as $skillNameOrPath) {
                app(SkillRegistry::class)->load($skillNameOrPath);
            }
        }
    }

    /**
     * Get the list of skills to be loaded.
     *
     * @return iterable<int, string>
     */
    public function skills(): iterable
    {
        return [];
    }

    /**
     * Get skill tools including meta-tools (ListSkills, LoadSkill) and loaded skill tools.
     *
     * @return array<int, Tool>
     */
    public function skillTools(): array
    {
        $registry = app(SkillRegistry::class);

        return array_merge(
            [
                new ListSkills($registry),
                new LoadSkill($registry),
            ],
            $registry->tools()
        );
    }

    /**
     * Get the combined instructions from all loaded skills.
     */
    public function skillInstructions(): string
    {
        return app(SkillRegistry::class)->instructions();
    }
}
