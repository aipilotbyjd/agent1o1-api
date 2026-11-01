<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A provider took the request to register a watch and refused it in words (a
 * filter it doesn't support, a limit reached) rather than with an error status.
 * Trying again won't change the answer, so the trigger keeps polling and the
 * message is shown (SyncTriggerSubscription).
 */
class WatchRefusedException extends RuntimeException {}
