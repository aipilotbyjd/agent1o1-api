<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows\Concerns;

use App\Enums\Triggers\TriggerTargetType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * A folder id taken from a request body is only valid when the folder belongs
 * to the workspace in the route and holds the right kind of item. A bare
 * `exists:folders,id` accepts another tenant's folder, which would let a
 * workflow or agent be filed under (and expose the name of) a folder in a
 * workspace the caller has no access to.
 *
 * Requires a `{workspace}` route parameter.
 */
trait ValidatesWorkspaceFolder
{
    protected function workspaceFolderExists(TriggerTargetType $type): Exists
    {
        return Rule::exists('folders', 'id')
            ->where('workspace_id', $this->route('workspace')?->id)
            ->where('type', $type->value);
    }
}
