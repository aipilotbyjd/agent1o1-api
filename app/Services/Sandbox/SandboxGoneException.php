<?php

namespace App\Services\Sandbox;

use RuntimeException;

/**
 * The provider already deleted the sandbox (it sat idle too long).
 */
class SandboxGoneException extends RuntimeException {}
