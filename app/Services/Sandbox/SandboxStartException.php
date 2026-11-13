<?php

namespace App\Services\Sandbox;

use RuntimeException;

/**
 * The provider could not be reached or could not start an environment, so
 * the code never ran. Only this kind of failure lets the router try the
 * next provider: after code has started, running it again could repeat its
 * side effects.
 */
class SandboxStartException extends RuntimeException {}
