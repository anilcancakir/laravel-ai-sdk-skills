<?php

namespace Illuminate\Contracts\JsonSchema;

if (! interface_exists(JsonSchema::class)) {
    interface JsonSchema {}
}

namespace Laravel\Ai\Contracts;

use Illuminate\Contracts\JsonSchema\JsonSchema;

if (! interface_exists(Tool::class)) {
    interface Tool
    {
        public function name(): string;

        public function description(): \Stringable|string;

        public function schema(JsonSchema $schema): array;

        public function handle(\Laravel\Ai\Tools\Request $request): \Stringable|string;
    }
}

namespace Laravel\Ai\Tools;

if (! class_exists(Request::class)) {
    class Request implements \ArrayAccess
    {
        public function __construct(protected array $arguments) {}

        public function input(string $key, $default = null)
        {
            return $this->arguments[$key] ?? $default;
        }

        public function offsetExists($offset): bool
        {
            return isset($this->arguments[$offset]);
        }

        public function offsetGet($offset): mixed
        {
            return $this->arguments[$offset];
        }

        public function offsetSet($offset, $value): void
        {
            $this->arguments[$offset] = $value;
        }

        public function offsetUnset($offset): void
        {
            unset($this->arguments[$offset]);
        }
    }
}
