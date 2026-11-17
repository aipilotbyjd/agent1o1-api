<?php

namespace App\Enums\Billing;

/**
 * Why a customer is cancelling, as picked on the first screen of the
 * cancellation flow. The order here is the order the screen lists them in.
 */
enum CancellationReason: string
{
    case TooExpensive = 'too_expensive';
    case NotUsingEnough = 'not_using_enough';
    case MissingFeature = 'missing_feature';
    case TooHard = 'too_hard';
    case SwitchingTool = 'switching_tool';
    case ProjectEnded = 'project_ended';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TooExpensive => 'It costs too much',
            self::NotUsingEnough => 'I am not using it enough',
            self::MissingFeature => 'It is missing something I need',
            self::TooHard => 'It is too hard to use, or things keep failing',
            self::SwitchingTool => 'I am switching to another tool',
            self::ProjectEnded => 'My project has ended',
            self::Other => 'Something else',
        };
    }

    /**
     * The question the free-text box asks for this reason.
     */
    public function commentPrompt(): string
    {
        return match ($this) {
            self::MissingFeature => 'What is missing?',
            self::TooHard => 'What went wrong?',
            self::SwitchingTool => 'Which tool are you moving to?',
            default => 'Anything else you would like us to know?',
        };
    }
}
