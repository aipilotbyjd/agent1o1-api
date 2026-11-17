<?php

namespace App\Jobs\Broadcasts;

/**
 * The old name of `DeliverBroadcastRecipientJob`, from when campaigns were
 * email only. Kept so jobs queued under this name before a deploy still run;
 * nothing dispatches it any more. Safe to delete once no such job is left
 * on the `system-broadcast` queue.
 *
 * @deprecated Use `DeliverBroadcastRecipientJob`.
 */
class SendBroadcastEmailJob extends DeliverBroadcastRecipientJob {}
