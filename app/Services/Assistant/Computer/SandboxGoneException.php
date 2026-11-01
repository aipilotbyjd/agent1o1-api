<?php

namespace App\Services\Assistant\Computer;

use RuntimeException;

/**
 * The provider already deleted the sandbox (it sat idle too long).
 */
class SandboxGoneException extends RuntimeException {}
