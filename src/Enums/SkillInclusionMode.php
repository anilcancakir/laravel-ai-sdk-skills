<?php

namespace AnilcanCakir\LaravelAiSdkSkills\Enums;

enum SkillInclusionMode: string
{
    case Lite = 'lite';
    case Full = 'full';

    /**
     * Parse enum, canonical strings, and alias strings.
     */
    public static function tryFromInput(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'lite', 'lazy' => self::Lite,
            'full', 'eager' => self::Full,
            default => null,
        };
    }
}
