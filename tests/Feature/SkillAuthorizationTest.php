<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Feature;

use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use AnilcanCakir\LaravelAiSdkSkills\Tools\SkillLoader;
use AnilcanCakir\LaravelAiSdkSkills\Traits\Skillable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Tools\Request;

class SkillAuthorizationTest extends TestCase
{
    public function test_skill_loader_refuses_undeclared_skill_when_enforcement_enabled(): void
    {
        config(['skills.enforce_declared' => true]);
        Log::spy();

        $agent = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['safe-skill'];
            }
        };

        // Take the loader from the agent rather than building one, so the allowlist under
        // test is this agent's declared list. A hand-built loader carries no list, which
        // under enforcement refuses everything and would pass for the wrong reason.
        $loader = collect($agent->skillTools())->first(
            fn (object $tool): bool => $tool instanceof SkillLoader
        );

        $registry = $this->app->make(SkillRegistry::class);

        $result = (string) $loader->handle(new Request(['name' => 'admin-only-skill']));

        // Proof of the bug: an undeclared skill must never enter the registry.
        $this->assertFalse($registry->isLoaded('admin-only-skill'));
        $this->assertStringNotContainsString('SECRET_ADMIN_INSTRUCTIONS', $result);
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_a_hand_built_loader_refuses_rather_than_serving_every_booted_agents_skills(): void
    {
        config(['skills.enforce_declared' => true]);

        $admin = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['admin-only-skill'];
            }
        };

        $safe = new class
        {
            use Skillable;

            public function skills(): iterable
            {
                return ['safe-skill'];
            }
        };

        $admin->skillTools();
        $safe->skillTools();

        $registry = $this->app->make(SkillRegistry::class);

        // A loader built outside Skillable cannot say which agent it speaks for. It must refuse
        // rather than serve the union of every agent booted in this request, which is the shape
        // issue #6 reported: one shared discovery directory across two trust levels.
        $result = (string) (new SkillLoader($registry))->handle(new Request(['name' => 'admin-only-skill']));

        $this->assertStringNotContainsString('SECRET_ADMIN_INSTRUCTIONS', $result);
    }

    public function test_skill_loader_must_refuse_absolute_path_outside_configured_roots(): void
    {
        $outsidePath = storage_path('temp-skills/outside-root-skill');
        File::makeDirectory($outsidePath, 0755, true, true);
        File::put($outsidePath.'/SKILL.md', <<<'EOT'
---
name: outside-root-skill
description: A skill living outside every configured root
---

# Outside Root Skill

Should never be reachable without a declaration or a containment check.
EOT
        );

        $registry = $this->app->make(SkillRegistry::class);
        $loader = new SkillLoader($registry);

        $loader->handle(new Request(['name' => $outsidePath]));

        // Proof: the content sits in the registry today, reachable via skill_read, despite living outside every root.
        $this->assertFalse($registry->isLoaded('outside-root-skill'));

    }

    public function test_skill_loader_must_refuse_absolute_path_when_configured_root_does_not_exist(): void
    {
        config(['skills.paths' => ['project' => storage_path('nonexistent-skills-root')]]);

        $outsidePath = storage_path('temp-skills/fresh-install-skill');
        File::makeDirectory($outsidePath, 0755, true, true);
        File::put($outsidePath.'/SKILL.md', <<<'EOT'
---
name: fresh-install-skill
description: A skill loaded while the only configured root is missing
---

# Fresh Install Skill

Should never be reachable when the configured root's realpath() fails.
EOT
        );

        $registry = $this->app->make(SkillRegistry::class);
        $loader = new SkillLoader($registry);

        $loader->handle(new Request(['name' => $outsidePath]));

        // Proof: a missing configured root, the default fresh-install state, does not stop the bypass.
        $this->assertFalse($registry->isLoaded('fresh-install-skill'));

    }

    public function test_skill_loader_still_loads_declared_absolute_path_skill_by_resolved_slug(): void
    {
        config(['skills.enforce_declared' => true]);

        $tempPath = storage_path('temp-skills/declared-path-skill');
        File::makeDirectory($tempPath, 0755, true, true);
        File::put($tempPath.'/SKILL.md', <<<'EOT'
---
name: declared-path-skill
description: A path-declared skill that must still load under enforcement
---

# Declared Path Skill

Loaded via a declared absolute path.
EOT
        );

        $agent = new class($tempPath)
        {
            use Skillable;

            public function __construct(protected string $path) {}

            public function skills(): iterable
            {
                return [$this->path];
            }
        };

        $agent->skillTools();

        $registry = $this->app->make(SkillRegistry::class);

        // Regression guard: enforcement must key the allowlist off the resolved slug, not the raw declared string.
        $this->assertTrue($registry->isLoaded('declared-path-skill'));
    }

    /**
     * Remove the temporary skill directories these tests write.
     *
     * This runs in tearDown rather than at the end of each test body so a failed
     * assertion cannot leave a skill behind for the rest of the suite.
     */
    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('temp-skills'));

        parent::tearDown();
    }
}
