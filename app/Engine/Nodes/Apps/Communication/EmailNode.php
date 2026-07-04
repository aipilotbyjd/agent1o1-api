<?php

namespace App\Engine\Nodes\Apps\Communication;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\Mail\MailNode;

class EmailNode extends MailNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        // EmailNode is an alias for MailNode with 'send_email' operation support
        return parent::execute($operation === 'send_email' ? 'send' : $operation, $input);
    }
}
