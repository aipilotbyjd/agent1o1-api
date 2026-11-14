<?php

namespace App\Enums\Changelog;

/**
 * What kind of change a changelog entry announces.
 */
enum ChangelogCategory: string
{
    case Feature = 'feature';
    case Improvement = 'improvement';
    case Fix = 'fix';
    case Security = 'security';
    case Deprecation = 'deprecation';
    case Breaking = 'breaking';

    public function label(): string
    {
        return match ($this) {
            self::Feature => 'New feature',
            self::Improvement => 'Improvement',
            self::Fix => 'Fix',
            self::Security => 'Security',
            self::Deprecation => 'Deprecation',
            self::Breaking => 'Breaking change',
        };
    }
}
