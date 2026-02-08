<?php

namespace Laravel\AI\Contracts;

if (! interface_exists('Laravel\AI\Contracts\Tool')) {
    interface Tool
    {
        public function name(): string;

        public function description(): string;

        public function schema(): array;

        public function handle(array $arguments): string;
    }
}
