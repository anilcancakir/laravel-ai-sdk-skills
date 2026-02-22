<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Support;

use Illuminate\Support\Facades\View;
use InvalidArgumentException;

/**
 * An immutable value object for composing AI agent prompt content.
 *
 * Supports multiple content sources: inline text with variable binding,
 * file-based templates, and Blade view rendering.
 *
 * Usage:
 *
 *     // Inline text with {{variable}} binding
 *     Prompt::text('You are {{role}}', ['role' => 'coach'])
 *
 *     // File-based prompt
 *     Prompt::file(resource_path('prompts/coach.md'), ['name' => $name])
 *
 *     // Blade view
 *     Prompt::view('prompts.coach', ['session' => $session])
 */
final class Prompt implements \Stringable
{
    private function __construct(
        private readonly string $content,
    ) {}

    /**
     * Create a prompt from inline text with optional variable binding.
     *
     * Variables use the {{key}} syntax and are replaced via simple string substitution.
     *
     * @param  string  $template  The template string, may contain {{key}} placeholders.
     * @param  array<string, string|int|float|\Stringable>  $data  Key-value pairs to replace in the template.
     */
    public static function text(string $template, array $data = []): self
    {
        return new self(self::bind($template, $data));
    }

    /**
     * Create a prompt from a file path with optional variable binding.
     *
     * Supports any text-based file format (md, txt, html, etc.).
     * Variables in the file content are replaced using {{key}} syntax.
     *
     * @param  string  $path  Absolute path to the prompt file.
     * @param  array<string, string|int|float|\Stringable>  $data  Key-value pairs to replace in the file content.
     *
     * @throws InvalidArgumentException If the file does not exist.
     */
    public static function file(string $path, array $data = []): self
    {
        if (! file_exists($path)) {
            throw new InvalidArgumentException("Prompt file not found: [{$path}]");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new InvalidArgumentException("Failed to read prompt file: [{$path}]");
        }

        return new self(self::bind(trim($content), $data));
    }

    /**
     * Create a prompt by rendering a Blade view.
     *
     * The view is rendered to a string using Laravel's View factory.
     * All Blade features (directives, components, etc.) are available.
     *
     * @param  string  $view  The view name (e.g. 'prompts.coach' or 'skills::prompts.coach').
     * @param  array<string, mixed>  $data  Data to pass to the Blade view.
     *
     * @throws InvalidArgumentException If the view does not exist.
     */
    public static function view(string $view, array $data = []): self
    {
        if (! View::exists($view)) {
            throw new InvalidArgumentException("Prompt view not found: [{$view}]");
        }

        return new self(trim(View::make($view, $data)->render()));
    }

    /**
     * Get the resolved prompt content as a string.
     */
    public function toString(): string
    {
        return $this->content;
    }

    /**
     * @see toString()
     */
    public function __toString(): string
    {
        return $this->content;
    }

    /**
     * Replace {{key}} placeholders in a template with values from the data array.
     *
     * @param  string  $template  The template string.
     * @param  array<string, string|int|float|\Stringable>  $data  Replacement values.
     */
    private static function bind(string $template, array $data): string
    {
        if ($data === []) {
            return $template;
        }

        $replacements = [];

        foreach ($data as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }
}