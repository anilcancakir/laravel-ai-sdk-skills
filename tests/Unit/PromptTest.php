<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Tests\Unit;

use AnilcanCakir\LaravelAiSdkSkills\Support\Prompt;
use AnilcanCakir\LaravelAiSdkSkills\Tests\TestCase;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Stringable;

class PromptTest extends TestCase
{
    // -------------------------------------------------------
    // Prompt::text()
    // -------------------------------------------------------

    public function test_text_returns_raw_string_without_data(): void
    {
        $prompt = Prompt::text('Hello world');
        $this->assertSame('Hello world', $prompt->toString());
    }

    public function test_text_replaces_single_variable(): void
    {
        $prompt = Prompt::text('Hello {{name}}', ['name' => 'John']);
        $this->assertSame('Hello John', $prompt->toString());
    }

    public function test_text_replaces_multiple_variables(): void
    {
        $prompt = Prompt::text('{{greeting}} {{name}}, you are a {{role}}.', [
            'greeting' => 'Hi',
            'name' => 'Alice',
            'role' => 'coach',
        ]);
        $this->assertSame('Hi Alice, you are a coach.', $prompt->toString());
    }

    public function test_text_leaves_unreplaced_placeholders_intact(): void
    {
        $prompt = Prompt::text('Hello {{name}}', []);
        $this->assertSame('Hello {{name}}', $prompt->toString());
    }

    public function test_text_handles_empty_template(): void
    {
        $prompt = Prompt::text('');
        $this->assertSame('', $prompt->toString());
    }

    public function test_text_handles_numeric_values(): void
    {
        $prompt = Prompt::text('Count: {{int}}, Price: {{float}}', [
            'int' => 42,
            'float' => 9.99,
        ]);
        $this->assertSame('Count: 42, Price: 9.99', $prompt->toString());
    }

    public function test_text_handles_stringable_values(): void
    {
        $stringable = new class implements Stringable
        {
            public function __toString(): string
            {
                return 'stringable-value';
            }
        };

        $prompt = Prompt::text('Value: {{obj}}', ['obj' => $stringable]);
        $this->assertSame('Value: stringable-value', $prompt->toString());
    }

    // -------------------------------------------------------
    // Prompt::file()
    // -------------------------------------------------------

    public function test_file_reads_content_from_path(): void
    {
        $path = $this->createTempFile('Hello from file.');

        $prompt = Prompt::file($path);
        $this->assertSame('Hello from file.', $prompt->toString());

        File::delete($path);
    }

    public function test_file_with_variable_binding(): void
    {
        $path = $this->createTempFile('Hello {{name}}, welcome.');

        $prompt = Prompt::file($path, ['name' => 'Bob']);
        $this->assertSame('Hello Bob, welcome.', $prompt->toString());

        File::delete($path);
    }

    public function test_file_trims_whitespace(): void
    {
        $path = $this->createTempFile("  \n  Content with whitespace  \n  ");

        $prompt = Prompt::file($path);
        $this->assertSame('Content with whitespace', $prompt->toString());

        File::delete($path);
    }

    public function test_file_throws_on_missing_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt file not found');

        Prompt::file('/nonexistent/path/to/file.md');
    }

    // -------------------------------------------------------
    // Prompt::view()
    // -------------------------------------------------------

    public function test_view_renders_blade_template(): void
    {
        $this->registerTempView('test-prompt', 'Hello from Blade.');

        $prompt = Prompt::view('test-prompts::test-prompt');
        $this->assertSame('Hello from Blade.', $prompt->toString());
    }

    public function test_view_passes_data_to_blade(): void
    {
        $this->registerTempView('greet', 'Hello {{ $name }}, you are {{ $role }}.');

        $prompt = Prompt::view('test-prompts::greet', ['name' => 'Alice', 'role' => 'coach']);
        $this->assertSame('Hello Alice, you are coach.', $prompt->toString());
    }

    public function test_view_throws_on_missing_view(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt view not found');

        Prompt::view('nonexistent.view.name');
    }

    public function test_view_trims_rendered_output(): void
    {
        $this->registerTempView('padded', '  Padded content  ');

        $prompt = Prompt::view('test-prompts::padded');
        $this->assertSame('Padded content', $prompt->toString());
    }

    // -------------------------------------------------------
    // Stringable / toString
    // -------------------------------------------------------

    public function test_to_string_returns_content(): void
    {
        $prompt = Prompt::text('cast me');
        $this->assertSame('cast me', (string) $prompt);
    }

    public function test_to_string_method_returns_content(): void
    {
        $prompt = Prompt::text('method call');
        $this->assertSame('method call', $prompt->toString());
    }

    public function test_implements_stringable(): void
    {
        $prompt = Prompt::text('test');
        $this->assertInstanceOf(Stringable::class, $prompt);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function createTempFile(string $content): string
    {
        $dir = storage_path('temp-prompts');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir.'/'.uniqid('prompt_').'.md';
        File::put($path, $content);

        return $path;
    }

    private function registerTempView(string $name, string $content): void
    {
        $dir = storage_path('temp-views');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($dir.'/'.$name.'.blade.php', $content);
        $this->app['view']->addNamespace('test-prompts', $dir);
    }
}
