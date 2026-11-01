<?php

namespace App\Enums\Assistant;

/**
 * The two things the assistant learns about how its owner likes to be
 * answered: the voice (Tone) and the shape of the answer (Design).
 */
enum AssistantStyleKind: string
{
    case Tone = 'tone';
    case Design = 'design';

    public function heading(): string
    {
        return match ($this) {
            self::Tone => 'How :user_name likes you to write',
            self::Design => 'How :user_name likes answers laid out',
        };
    }
}
