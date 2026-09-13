<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Support;

use AnilcanCakir\LaravelAiSdkSkills\Enums\SkillInclusionMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;

/**
 * Manages the registration and loading of AI skills.
 */
class SkillRegistry
{
    /**
     * The list of loaded skills.
     *
     * @var array<string, Skill>
     */
    protected array $loaded = [];

    /**
     * Inclusion mode preference for each loaded skill.
     *
     * @var array<string, SkillInclusionMode|null>
     */
    protected array $loadedModes = [];

    /**
     * Resolved slugs of the skills declared by the agents booted in this request.
     *
     * Null until an agent boots, which is what keeps a bare registry unrestricted.
     *
     * @var array<int, string>|null
     */
    protected ?array $declaredSlugs = null;

    /**
     * Create a new skill registry instance.
     *
     * @param  SkillDiscovery  $discovery  The skill discovery instance.
     * @return void
     */
    public function __construct(
        protected SkillDiscovery $discovery,
    ) {}

    /**
     * Load a skill by name or path.
     *
     * @param  string  $nameOrPath  The name or path of the skill to load.
     * @param  mixed  $mode  Optional per-skill inclusion mode (enum|string|null).
     * @return Skill|null The loaded skill or null on failure.
     */
    public function load(string $nameOrPath, mixed $mode = null): ?Skill
    {
        $skill = $this->discovery->resolve($nameOrPath);

        if ($skill) {
            $slug = $skill->slug();
            $resolvedMode = SkillInclusionMode::tryFromInput($mode);

            if ($mode !== null && $resolvedMode === null) {
                Log::warning(
                    "Invalid skill inclusion mode [{$this->stringifyMode($mode)}] for skill [{$nameOrPath}]. ".
                    'Falling back to configured discovery_mode.'
                );
            }

            $this->loaded[$slug] = $skill;
            if ($mode !== null) {
                $this->loadedModes[$slug] = $resolvedMode;
            }
        }

        return $skill;
    }

    /**
     * Record the resolved slugs an agent declares, so tools built outside it can enforce them.
     *
     * Declarations accumulate over the agents booted in this request, since the registry is
     * request scoped and every tool built through Skillable carries its own agent's list.
     *
     * @param  array<int, string>  $slugs  Resolved skill slugs.
     */
    public function declareSlugs(array $slugs): void
    {
        $this->declaredSlugs = array_values(array_unique(array_merge($this->declaredSlugs ?? [], $slugs)));
    }

    /**
     * Determine if a slug belongs to the effective declared-skills allowlist.
     *
     * This answers the allowlist question only; callers gate it on [skills.enforce_declared].
     * A null list means no agent declared anything, which allows every skill.
     *
     * @param  array<int, string>|null  $declaredSlugs  The caller's own allowlist, or null for the registry's.
     */
    public function declares(string $slug, ?array $declaredSlugs = null): bool
    {
        $allowed = $declaredSlugs ?? $this->declaredSlugs;

        return $allowed === null || in_array($slug, $allowed, true);
    }

    /**
     * Determine if a skill is loaded by name.
     *
     * @param  string  $name  The name of the skill.
     */
    public function isLoaded(string $name): bool
    {
        return isset($this->loaded[$name]);
    }

    /**
     * Get a loaded skill by name.
     *
     * @param  string  $name  The name of the skill.
     */
    public function get(string $name): ?Skill
    {
        return $this->loaded[$name] ?? null;
    }

    /**
     * Get all available skills.
     *
     * @return Collection<string, Skill>
     */
    public function available(): Collection
    {
        return $this->discovery->discover();
    }

    /**
     * Get all loaded skills.
     *
     * @return array<string, Skill>
     */
    public function getLoaded(): array
    {
        return $this->loaded;
    }

    /**
     * Get all tools from loaded skills.
     *
     * @param  array<int, string>|null  $declaredSlugs  Allowlist applied when enforcement is on; null allows all.
     * @return array<int, Tool>
     */
    public function tools(?array $declaredSlugs = null): array
    {
        $tools = [];

        foreach ($this->allowedLoaded($declaredSlugs) as $skill) {
            foreach ($skill->tools as $toolClass) {
                if (class_exists($toolClass)) {
                    $tools[] = app($toolClass);
                } else {
                    Log::warning("Tool class [{$toolClass}] not found for skill [{$skill->name}].");
                }
            }
        }

        return $tools;
    }

    /**
     * Get the combined instructions from all loaded skills.
     *
     * In 'lite' mode, only skill name and description are returned.
     * In 'full' mode, full instructions are included.
     *
     * @param  string|null  $mode  Global override for the inclusion mode ('lite' or 'full').
     * @param  array<int, string>|null  $declaredSlugs  Allowlist applied when enforcement is on; null allows all.
     */
    public function instructions(?string $mode = null, ?array $declaredSlugs = null): string
    {
        $configuredMode = $this->configuredMode();
        $globalOverrideMode = null;

        if ($mode !== null) {
            $globalOverrideMode = SkillInclusionMode::tryFromInput($mode);

            if ($globalOverrideMode === null) {
                Log::warning(
                    "Invalid instructions mode override [{$mode}]. ".
                    'Falling back to configured discovery_mode.'
                );
            }
        }

        $instructions = '';

        foreach ($this->allowedLoaded($declaredSlugs) as $slug => $skill) {
            $effectiveMode = $globalOverrideMode
                ?? ($this->loadedModes[$slug] ?? null)
                ?? $configuredMode;

            if ($effectiveMode === SkillInclusionMode::Full) {
                $instructions .= sprintf(
                    "<skill name=\"%s\">\n%s\n</skill>\n",
                    $skill->name,
                    trim($skill->instructions)
                );
            } else {
                $instructions .= sprintf(
                    "<skill name=\"%s\" description=\"%s\" />\n",
                    $skill->name,
                    $skill->description
                );
            }
        }

        return trim($instructions);
    }

    /**
     * Get the loaded skills the caller is allowed to see.
     *
     * @param  array<int, string>|null  $declaredSlugs  Allowlist applied when enforcement is on.
     * @return array<string, Skill>
     */
    private function allowedLoaded(?array $declaredSlugs): array
    {
        if (! config('skills.enforce_declared', false)) {
            return $this->loaded;
        }

        return array_filter(
            $this->loaded,
            fn (string $slug): bool => $this->declares($slug, $declaredSlugs),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function configuredMode(): SkillInclusionMode
    {
        $configured = config('skills.discovery_mode', SkillInclusionMode::Lite->value);
        $mode = SkillInclusionMode::tryFromInput($configured);

        if ($mode !== null) {
            return $mode;
        }

        Log::warning(
            "Invalid config value for [skills.discovery_mode]: [{$this->stringifyMode($configured)}]. ".
            'Defaulting to [lite].'
        );

        return SkillInclusionMode::Lite;
    }

    private function stringifyMode(mixed $mode): string
    {
        if ($mode instanceof SkillInclusionMode) {
            return $mode->value;
        }

        if (is_scalar($mode) || $mode === null) {
            return (string) $mode;
        }

        return get_debug_type($mode);
    }
}
