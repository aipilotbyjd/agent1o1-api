<?php

namespace App\Http\Requests\Api\V1\Workflow;

trait PreservesGraphPayload
{
    /**
     * Node and edge objects carry free-form keys (config, name, position, handles…)
     * that per-field rules like `nodes.*.id` cannot enumerate. Laravel's validated()
     * returns only validated keys, which would strip everything except id/type/source/
     * target and destroy the graph. Re-attach the full arrays after validation.
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated();

        foreach (['nodes', 'edges'] as $graphKey) {
            if ($this->has($graphKey)) {
                $validated[$graphKey] = $this->input($graphKey);
            }
        }

        return data_get($validated, $key, $default);
    }
}
