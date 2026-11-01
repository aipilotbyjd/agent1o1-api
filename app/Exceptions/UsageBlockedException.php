<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Work was refused before it started because the workspace cannot pay for
 * it right now: out of credits (`InsufficientCreditsException`) or over an
 * AI cost guardrail (`CostLimitReachedException`). Callers that skip work
 * quietly when a workspace is blocked catch this.
 */
abstract class UsageBlockedException extends RuntimeException {}
