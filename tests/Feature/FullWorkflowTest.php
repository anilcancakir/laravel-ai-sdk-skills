<?php

namespace App\Ai\Tools {
    use Laravel\AI\Contracts\Tool;

    if (! class_exists('App\Ai\Tools\GitCommit')) {
        class GitCommit implements Tool
        {
            public function name(): string
            {
                return 'git_commit';
            }

            public function description(): string
            {
                return 'Git Commit';
            }

            public function schema(): array
            {
                return [];
            }

            public function handle(array $arguments): string
            {
                return 'committed';
            }
        }
    }

    if (! class_exists('App\Ai\Tools\GitLog')) {
        class GitLog implements Tool
        {
            public function name(): string
            {
                return 'git_log';
            }

            public function description(): string
            {
                return 'Git Log';
            }

            public function schema(): array
            {
                return [];
            }

            public function handle(array $arguments): string
            {
                return 'logged';
            }
        }
    }

    if (! class_exists('App\Ai\Tools\SearchDocs')) {
        class SearchDocs implements Tool
        {
            public function name(): string
            {
                return 'search_docs';
            }

            public function description(): string
            {
                return 'Search Docs';
            }

            public function schema(): array
            {
                return [];
            }

            public function handle(array $arguments): string
            {
                return 'found';
            }
        }
    }
}

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Feature {
    use AnilcanCakir\LaravelAiSdkSkills\Support\Skill;
    use AnilcanCakir\LaravelAiSdkSkills\Support\SkillDiscovery;
    use AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry;
    use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
    use Mockery;

    class FullWorkflowTest extends TestCase
    {
        public function test_full_skill_lifecycle_discover_list_load(): void
        {
            $registry = $this->app->make(SkillRegistry::class);

            $available = $registry->available();
            $this->assertTrue($available->has('git-master'));
            $this->assertTrue($available->has('search-docs'));

            $registry->load('git-master');
            $this->assertTrue($registry->isLoaded('git-master'));
            $this->assertFalse($registry->isLoaded('search-docs'));

            $instructions = $registry->instructions();
            $this->assertStringContainsString('Git operations expert', $instructions);
            $this->assertStringContainsString('<skill name="git-master">', $instructions);
        }

        public function test_agent_with_skills_has_correct_tool_count(): void
        {
            $agent = new TestAgent;
            $registry = $agent->skillRegistry();

            $this->assertCount(2, $agent->skillTools());

            $registry->load('git-master');

            $this->assertCount(4, $agent->skillTools());
        }

        public function test_multiple_skills_can_be_loaded(): void
        {
            $registry = $this->app->make(SkillRegistry::class);

            $registry->load('git-master');
            $registry->load('search-docs');

            $this->assertCount(2, $registry->getLoaded());

            $instructions = $registry->instructions();
            $this->assertStringContainsString('git-master', $instructions);
            $this->assertStringContainsString('search-docs', $instructions);

            $this->assertCount(3, $registry->tools());
        }

        public function test_missing_tool_class_doesnt_crash_agent(): void
        {
            $mockDiscovery = Mockery::mock(SkillDiscovery::class);
            $mockDiscovery->shouldReceive('resolve')->with('broken-skill')->andReturn(new Skill(
                name: 'broken-skill',
                description: 'A broken skill',
                instructions: 'Do nothing',
                tools: ['NonExistentToolClass'],
                triggers: []
            ));

            $registry = new SkillRegistry($mockDiscovery);

            $registry->load('broken-skill');

            $tools = $registry->tools();

            $this->assertEmpty($tools);
        }
    }

    class TestAgent
    {
        use \AnilcanCakir\LaravelAiSdkSkills\Traits\Skillable;

        public function skillRegistry(): \AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry
        {
            return \app(\AnilcanCakir\LaravelAiSdkSkills\Support\SkillRegistry::class);
        }
    }
}
