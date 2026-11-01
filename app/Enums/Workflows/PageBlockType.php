<?php

namespace App\Enums\Workflows;

/**
 * The building blocks of a workflow page. `Field`, `Submit`, `Output` and
 * `Status` are bound to the workflow: a field to one of its interface
 * fields, an output to one of its Output steps. `Section`, `Columns` and
 * `Column` hold other blocks in their `children`; the rest are content.
 */
enum PageBlockType: string
{
    case Heading = 'heading';
    case Text = 'text';
    case Image = 'image';
    case Divider = 'divider';
    case Spacer = 'spacer';
    case Callout = 'callout';
    case Button = 'button';
    case Video = 'video';
    case Faq = 'faq';
    case Testimonial = 'testimonial';
    case Stats = 'stats';
    case Logos = 'logos';
    case Tabs = 'tabs';
    case Countdown = 'countdown';
    case Field = 'field';
    case Submit = 'submit';
    case Output = 'output';
    case Status = 'status';
    case Section = 'section';
    case Columns = 'columns';
    case Column = 'column';

    public function isContainer(): bool
    {
        return in_array($this, [self::Section, self::Columns, self::Column], true);
    }

    /**
     * Whether this block may sit directly inside `$parent`: null for the
     * page itself. A section sits on the page, columns on the page or in a
     * section, a column only in columns, and everything else anywhere but
     * directly in columns.
     */
    public function fitsIn(?self $parent): bool
    {
        return match ($this) {
            self::Section => $parent === null,
            self::Columns => $parent === null || $parent === self::Section,
            self::Column => $parent === self::Columns,
            default => $parent !== self::Columns,
        };
    }
}
