<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Discover and resolve skills from the filesystem or remote sources.
 */
class SkillDiscovery
{
    /**
     * Create a new skill discovery instance.
     *
     * @param  array  $paths  The paths to scan for local skills.
     * @param  string  $mode  The discovery mode (local, remote, dual).
     * @param  string|null  $remoteUrl  The URL for remote skill discovery.
     * @param  string|null  $remoteToken  The token for remote skill discovery.
     * @param  int  $remoteTimeout  The timeout for remote requests.
     * @param  bool  $cacheEnabled  Whether to enable caching.
     * @param  int  $cacheTtl  The cache time-to-live in seconds.
     * @return void
     */
    public function __construct(
        protected array $paths,
        protected string $mode = 'local',
        protected ?string $remoteUrl = null,
        protected ?string $remoteToken = null,
        protected int $remoteTimeout = 5,
        protected bool $cacheEnabled = true,
        protected int $cacheTtl = 3600,
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
                        'local',
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

        if ($this->mode === 'local' || $this->mode === 'dual') {
            $skills = $skills->merge($this->scanLocal());
        }

        if ($this->mode === 'remote' || $this->mode === 'dual') {
            $skills = $skills->merge($this->scanRemote());
        }

        return $skills;
    }

    /**
     * Scan the configured paths for local skills.
     *
     * @return Collection<string, Skill>
     */
    protected function scanLocal(): Collection
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
                    $skill = SkillParser::parse($file->getContents(), 'local', $file->getPath());

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

    /**
     * Scan the remote source for skills.
     *
     * @return Collection<string, Skill>
     */
    protected function scanRemote(): Collection
    {
        if (empty($this->remoteUrl)) {
            return new Collection;
        }

        try {
            $response = Http::withToken($this->remoteToken)
                ->timeout($this->remoteTimeout)
                ->get($this->remoteUrl);

            if ($response->failed()) {
                Log::warning("SkillDiscovery: Remote discovery failed with status [{$response->status()}].");

                return new Collection;
            }

            $skills = new Collection;
            $remoteSkills = $response->json('skills', []);

            foreach ($remoteSkills as $skillData) {
                try {
                    // If the remote provides the raw markdown, we parse it
                    if (isset($skillData['content'])) {
                        $skill = SkillParser::parse($skillData['content'], 'remote');
                        if ($skill) {
                            $skills->put($skill->slug(), $skill);
                        }

                        continue;
                    }

                    // Otherwise, we expect a pre-structured skill format
                    $skill = new Skill(
                        name: $skillData['name'],
                        description: $skillData['description'],
                        instructions: $skillData['instructions'] ?? '',
                        tools: $skillData['tools'] ?? [],
                        triggers: $skillData['triggers'] ?? [],
                        version: $skillData['version'] ?? null,
                        mcp: $skillData['mcp'] ?? [],
                        constraints: $skillData['constraints'] ?? [],
                        source: 'remote',
                    );

                    $skills->put($skill->slug(), $skill);
                } catch (Throwable $e) {
                    continue;
                }
            }

            return $skills;
        } catch (Throwable $e) {
            Log::warning("SkillDiscovery: Remote discovery error: {$e->getMessage()}");

            return new Collection;
        }
    }
}
