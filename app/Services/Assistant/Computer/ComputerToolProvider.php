<?php

namespace App\Services\Assistant\Computer;

use App\Actions\Artifacts\StoreArtifactAction;
use App\Ai\Assistant\Tools\ExportFileTool;
use App\Ai\Assistant\Tools\RunCodeTool;
use App\Contracts\Assistant\ProvidesAssistantTools;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;
use App\Services\Artifacts\DocumentRenderer;

/**
 * Making things: files for the owner, and (where the plan and server allow
 *) a cloud computer to run code on. Incognito conversations leave nothing
 * behind, so they get neither.
 */
class ComputerToolProvider implements ProvidesAssistantTools
{
    public function __construct(
        private readonly Sandboxes $sandboxes,
        private readonly StoreArtifactAction $store,
        private readonly DocumentRenderer $renderer,
    ) {}

    public function toolsFor(Assistant $assistant, AssistantSession $session): array
    {
        if ($session->incognito) {
            return [];
        }

        $sandboxes = $this->sandboxes->available($assistant) ? $this->sandboxes : null;

        return array_values(array_filter([
            new ExportFileTool($session, $this->store, $this->renderer, $sandboxes),
            $sandboxes !== null ? new RunCodeTool($session, $sandboxes) : null,
        ]));
    }
}
