<?php

namespace App\Enums;

enum JobRole: string
{
    case Sales = 'sales';
    case Marketing = 'marketing';
    case Operations = 'operations';
    case Support = 'support';
    case Engineering = 'engineering';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Sales',
            self::Marketing => 'Marketing',
            self::Operations => 'Operations',
            self::Support => 'Support',
            self::Engineering => 'Engineering',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Sales => 'Personalize agents with CRM and messaging platforms.',
            self::Marketing => 'Automate content workflows and marketing metrics.',
            self::Operations => 'Connect search and spreadsheets for productivity.',
            self::Support => 'Integrate tools for fast tickets response.',
            self::Engineering => 'Power up dev workflows, code runner and git systems.',
        };
    }
}
