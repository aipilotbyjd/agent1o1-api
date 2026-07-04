<?php

namespace App\Enums;

enum BuilderMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
