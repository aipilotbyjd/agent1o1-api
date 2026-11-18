<?php

namespace App\Enums\Templates;

/**
 * The kinds of template the combined catalog lists side by side.
 */
enum TemplateType: string
{
    case Workflow = 'workflow';
    case Agent = 'agent';
    case Collection = 'collection';
    case Page = 'page';

    public function label(): string
    {
        return match ($this) {
            self::Workflow => 'Workflow',
            self::Agent => 'Agent',
            self::Collection => 'Collection',
            self::Page => 'Page',
        };
    }
}
