<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class SkillPublishBlocked extends RuntimeException implements ShouldntReport {}
