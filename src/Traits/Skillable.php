<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Traits;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tools\ListSkills;
use AnilcanCakir\LaravelAiSdkSkills\Tools\SkillLoader;
use AnilcanCakir\LaravelAiSdkSkills\Tools\SkillReferenceReader;
use Illuminate\Support\Facades\Log;
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
     * Supports:
     * - list form: ['my-skill', '/abs/path/to/skill']
     * - keyed form: ['my-skill' => 'full', 'other-skill' => 'lite']
     *
     * @return iterable<int|string, mixed>
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
     * Compose full system instructions with skill instructions inserted in the middle.
     *
     * The dynamic prompt is appended last for prompt-caching-friendly ordering.
     *
     * @param  string  $staticPrompt  The stable/base system prompt content to place first.
     * @param  string  $dynamicPrompt  Runtime-varying prompt content to append at the end.
     */
    public function withSkillInstructions(string $staticPrompt = '', string $dynamicPrompt = ''): string
    {
        $segments = [$staticPrompt, $this->skillInstructions(), $dynamicPrompt];
        $segments = array_values(array_filter(
            $segments,
            static fn (string $segment): bool => trim($segment) !== ''
        ));

        return implode("\n\n", $segments);
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
        $registry = app(SkillRegistry::class);

        foreach ($this->skills() as $key => $value) {
            if (is_int($key)) {
                if (! is_string($value)) {
                    Log::warning(
                        'Invalid skill entry in skills(): numeric-key entries must be skill strings; got ['.
                        get_debug_type($value).']. Skipping entry.'
                    );

                    continue;
                }

                $registry->load($value);

                continue;
            }

            if (! is_string($key)) {
                Log::warning(
                    'Invalid skill entry key in skills(): expected string key for skill => mode mapping; got ['.
                    get_debug_type($key).']. Skipping entry.'
                );

                continue;
            }

            $registry->load($key, $value);
        }
    }
}
