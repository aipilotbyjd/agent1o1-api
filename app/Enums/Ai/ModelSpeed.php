<?php

namespace App\Enums\Ai;

/**
 * How quickly a catalog model answers, relative to the rest of the catalog:
 * shown in the model picker.
 */
enum ModelSpeed: string
{
    case Fast = 'fast';
    case Balanced = 'balanced';
    case Thorough = 'thorough';
}
