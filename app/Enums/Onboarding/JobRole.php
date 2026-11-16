<?php

namespace App\Enums\Onboarding;

enum JobRole: string
{
    case Sales = 'sales';
    case Marketing = 'marketing';
    case Operations = 'operations';
    case Support = 'support';
    case Engineering = 'engineering';
    case Product = 'product';
    case Security = 'security';
    case HR = 'hr';
    case Legal = 'legal';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Sales',
            self::Marketing => 'Marketing',
            self::Operations => 'Operations',
            self::Support => 'Support',
            self::Engineering => 'Engineering',
            self::Product => 'Product',
            self::Security => 'Security',
            self::HR => 'HR',
            self::Legal => 'Legal',
            self::Finance => 'Finance',
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
            self::Product => 'Manage task-boards and documents smoothly.',
            self::Security => 'Handle logins, logs and notifications.',
            self::HR => 'Onboard team members and schedule calendars.',
            self::Legal => 'Streamline contracts and files databases.',
            self::Finance => 'Sync invoice systems with data sheets.',
        };
    }

    /**
     * The template categories to suggest first to someone in this role, most
     * relevant first. Roles with no category of their own borrow the nearest.
     *
     * @return array<int, string>
     */
    public function templateCategories(): array
    {
        return match ($this) {
            self::Sales => ['Sales & Outreach'],
            self::Marketing => ['Marketing & Content'],
            self::Operations => ['Productivity & Ops', 'Data & Analytics'],
            self::Support => ['Support & Success'],
            self::Engineering, self::Security => ['Engineering & IT'],
            self::Product => ['Productivity & Ops', 'Data & Analytics'],
            self::HR => ['HR & Recruiting'],
            self::Legal => ['Legal & Compliance'],
            self::Finance => ['Finance & Accounting'],
        };
    }
}
