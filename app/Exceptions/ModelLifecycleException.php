<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A model could not be deprecated, retired or restored as asked (no
 * replacement, the replacement is itself unavailable, ...). The message is
 * written for the operator who asked.
 */
class ModelLifecycleException extends RuntimeException {}
