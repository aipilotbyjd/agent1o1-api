<?php

namespace App\Exceptions\Workspace;

use Exception;

class NotAMemberException extends Exception
{
    public function __construct()
    {
        parent::__construct('You are not a member of this workspace.', 403);
    }
}
