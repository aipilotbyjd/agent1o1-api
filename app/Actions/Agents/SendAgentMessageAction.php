<?php

namespace App\Actions\Agents;

use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Agents\Skill;
use App\Services\Agents\AgentRunner;
use Illuminate\Http\UploadedFile;

class SendAgentMessageAction
{
    public function __construct(private readonly AgentRunner $runner) {}

    /**
     * `$triggerType` is recorded on the turn's own `Run`: it defaults to
     * `manual` (a person chatting through the API) and is set to the trigger's
     * type when the turn was started by the trigger pipeline
     * (`App\Services\Triggers\TargetRunStarter`).
     *
     * @param  array<int, UploadedFile>  $attachments  Files the member attached to this message.
     * @param  string|null  $replacing  An earlier user message this one replaces: see `AgentRunner::run()`.
     */
    public function execute(AgentSession $session, string $message, string $triggerType = 'manual', array $attachments = [], ?Skill $skill = null, ?string $replacing = null): AgentMessage
    {
        return $this->runner->run($session, $message, $triggerType, $attachments, $skill, $replacing);
    }
}
