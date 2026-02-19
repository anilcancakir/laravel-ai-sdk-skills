<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Discover and resolve skills from the filesystem.
 */
class SkillDiscovery
{
    /**
     * Create a new skill discovery instance.
     *
     * @param  array  $paths  The paths to scan for local skills.
     * @param  bool  $cacheEnabled  Whether to enable caching.
     * @param  int  $cacheTtl  The cache time-to-live in seconds.
     * @param  string|null  $cacheStore  The cache store to use for skills cache.
     * @return void
     */
    public function __construct(
        protected array $paths,
        protected bool $cacheEnabled = true,
        protected int $cacheTtl = 3600,
        protected ?string $cacheStore = null,
    ) {}

    /**
     * Resolve a skill by name or direct path.
     *
     * @param  string  $nameOrPath  The name or path of the skill.
     */
    public function resolve(string $nameOrPath): ?Skill
    {
        if (File::isDirectory($nameOrPath)) {
            $skillFile = rtrim($nameOrPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'SKILL.md';

            if (File::exists($skillFile)) {
                try {
                    return SkillParser::parse(
                        File::get($skillFile),
                        rtrim($nameOrPath, DIRECTORY_SEPARATOR),
                    );
                } catch (Throwable $e) {
                    return null;
                }
            }
        }

        return $this->discover()->get($nameOrPath);
    }

    /**
     * Discover all available skills.
     *
     * @return Collection<string, Skill>
     */
    public function discover(): Collection
    {
        if ($this->cacheEnabled) {
            if ($this->cacheStore !== null) {
                return Cache::store($this->cacheStore)->remember('ai_sdk_skills', $this->cacheTtl, fn () => $this->scan());
            }

            return Cache::remember('ai_sdk_skills', $this->cacheTtl, fn () => $this->scan());
        }

        return $this->scan();
    }

    /**
     * Discover skills without using the cache.
     *
     * @return Collection<string, Skill>
     */
    public function fresh(): Collection
    {
        $this->clearCache();

        return $this->discover();
    }

    /**
     * Clear the skills cache.
     */
    public function clearCache(): void
    {
        if ($this->cacheEnabled) {
            if ($this->cacheStore !== null) {
                Cache::store($this->cacheStore)->forget('ai_sdk_skills');

                return;
            }

            Cache::forget('ai_sdk_skills');
        }
    }

    /**
     * Scan the configured sources for skills.
     *
     * @return Collection<string, Skill>
     */
    protected function scan(): Collection
    {
        $skills = new Collection;

        foreach ($this->paths as $path) {
            if (! File::isDirectory($path)) {
                continue;
            }

            $finder = Finder::create()
                ->in($path)
                ->followLinks()
                ->files()
                ->depth(1)
                ->name('SKILL.md');

            foreach ($finder as $file) {
                try {
                    $skill = SkillParser::parse($file->getContents(), $file->getPath());

                    if ($skill) {
                        $skills->put($skill->slug(), $skill);
                    }
                } catch (Throwable $e) {
                    continue;
                }
            }
        }

        return $skills;
    }
}
