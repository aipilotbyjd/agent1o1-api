<?php

namespace App\Services\Sandbox;

use RuntimeException;

/**
 * No provider can take the work right now: the feature is switched off, a
 * limit was reached, none is set up, or every one failed. The message is
 * safe to show the person.
 */
class SandboxUnavailableException extends RuntimeException {}
