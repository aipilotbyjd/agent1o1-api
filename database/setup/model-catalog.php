<?php

/**
 * The public model catalog: each entry's identity, capabilities and routes,
 * plus what the model picker says about it. See `ModelCatalogDataset` for
 * which of these an admin keeps control of after the first sync.
 */
return [
    'models' => [
        // --- Anthropic ---------------------------------------------------
        [
            'slug' => 'claude-fable-5',
            'display_name' => 'Claude Fable 5',
            'brand' => 'anthropic',
            'capabilities' => ['context_window' => 200000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-fable-5', 'priority' => 0, 'is_enabled' => true],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'anthropic/claude-fable-5', 'priority' => 1, 'is_enabled' => false],
            ],
        ],

        [
            'slug' => 'claude-opus-5',
            'display_name' => 'Claude Opus 5',
            'brand' => 'anthropic',
            'capabilities' => ['context_window' => 200000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-opus-5', 'priority' => 0, 'is_enabled' => true],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'anthropic/claude-opus-5', 'priority' => 1, 'is_enabled' => false],
            ],
        ],

        [
            'slug' => 'claude-sonnet-5',
            'display_name' => 'Claude Sonnet 5',
            'brand' => 'anthropic',
            'capabilities' => ['context_window' => 200000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-sonnet-5', 'priority' => 0, 'is_enabled' => true],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'anthropic/claude-sonnet-5', 'priority' => 1, 'is_enabled' => false],
                // Bedrock also hosts Anthropic models under its own naming
                // scheme: verify the exact cross-region inference profile id
                // before enabling.
                ['execution_provider' => 'bedrock', 'execution_model_id' => 'anthropic.claude-sonnet-5-v1:0', 'priority' => 2, 'is_enabled' => false],
            ],
        ],

        [
            'slug' => 'claude-haiku-4-5',
            'display_name' => 'Claude Haiku 4.5',
            'brand' => 'anthropic',
            'capabilities' => ['context_window' => 200000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-haiku-4-5-20251001', 'priority' => 0, 'is_enabled' => true],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'anthropic/claude-haiku-4.5', 'priority' => 1, 'is_enabled' => false],
            ],
        ],

        // --- OpenAI --------------------------------------------------------
        [
            'slug' => 'gpt-5.6-sol',
            'display_name' => 'GPT-5.6 Sol',
            'brand' => 'openai',
            'capabilities' => ['context_window' => 1050000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-5.6-sol', 'priority' => 0, 'is_enabled' => true],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'openai/gpt-5.6-sol', 'priority' => 1, 'is_enabled' => false],
            ],
        ],

        [
            'slug' => 'gpt-5.6-terra',
            'display_name' => 'GPT-5.6 Terra',
            'brand' => 'openai',
            'capabilities' => ['context_window' => 1050000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-5.6-terra', 'priority' => 0, 'is_enabled' => true],
            ],
        ],

        [
            'slug' => 'gpt-5.6-luna',
            'display_name' => 'GPT-5.6 Luna',
            'brand' => 'openai',
            'capabilities' => ['context_window' => 1050000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-5.6-luna', 'priority' => 0, 'is_enabled' => true],
            ],
        ],

        // Kept as still-available previous-generation entries: agents
        // already pointed at these keep working unchanged.
        [
            'slug' => 'gpt-4o',
            'display_name' => 'GPT-4o',
            'brand' => 'openai',
            'capabilities' => ['context_window' => 128000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 0, 'is_enabled' => true],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'openai/gpt-4o', 'priority' => 1, 'is_enabled' => false],
            ],
        ],

        [
            'slug' => 'gpt-4o-mini',
            'display_name' => 'GPT-4o mini',
            'brand' => 'openai',
            'capabilities' => ['context_window' => 128000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o-mini', 'priority' => 0, 'is_enabled' => true],
            ],
        ],

        // --- Google ----------------------------------------------------
        [
            'slug' => 'gemini-3.1-pro',
            'display_name' => 'Gemini 3.1 Pro',
            'brand' => 'google',
            'capabilities' => ['context_window' => 1000000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'gemini', 'execution_model_id' => 'gemini-3.1-pro', 'priority' => 0, 'is_enabled' => true],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'google/gemini-3.1-pro', 'priority' => 1, 'is_enabled' => false],
            ],
        ],

        [
            'slug' => 'gemini-3.5-flash',
            'display_name' => 'Gemini 3.5 Flash',
            'brand' => 'google',
            'capabilities' => ['context_window' => 1000000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'gemini', 'execution_model_id' => 'gemini-3.5-flash', 'priority' => 0, 'is_enabled' => true],
            ],
        ],

        // --- xAI ---------------------------------------------------------
        [
            'slug' => 'grok-4.6',
            'display_name' => 'Grok 4.6',
            'brand' => 'xai',
            'capabilities' => ['context_window' => 500000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'xai', 'execution_model_id' => 'grok-4.6', 'priority' => 0, 'is_enabled' => true],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'x-ai/grok-4.6', 'priority' => 1, 'is_enabled' => false],
            ],
        ],

        // --- Mistral -------------------------------------------------------
        [
            'slug' => 'mistral-medium-3.5',
            'display_name' => 'Mistral Medium 3.5',
            'brand' => 'mistral',
            'capabilities' => ['context_window' => 128000, 'vision' => false, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'mistral', 'execution_model_id' => 'mistral-medium-3.5', 'priority' => 0, 'is_enabled' => true],
            ],
        ],

        [
            'slug' => 'mistral-small-4',
            'display_name' => 'Mistral Small 4',
            'brand' => 'mistral',
            'capabilities' => ['context_window' => 256000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'xkiro', 'execution_model_id' => 'mistralai/mistral-small-2603', 'priority' => 0, 'is_enabled' => true],
            ],
        ],

        // --- DeepSeek (has its own direct API, plus aggregator routes) -----
        [
            'slug' => 'deepseek-v4-pro',
            'display_name' => 'DeepSeek V4 Pro',
            'brand' => 'deepseek',
            'capabilities' => ['context_window' => 1040000, 'vision' => false, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'deepseek', 'execution_model_id' => 'deepseek-v4-pro', 'priority' => 0, 'is_enabled' => true],
                ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/deepseek-v4p', 'priority' => 1, 'is_enabled' => false],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'deepseek/deepseek-v4-pro', 'priority' => 2, 'is_enabled' => false],
            ],
        ],

        [
            'slug' => 'deepseek-v4-flash',
            'display_name' => 'DeepSeek V4 Flash',
            'brand' => 'deepseek',
            'capabilities' => ['context_window' => 1000000, 'vision' => false, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'deepseek', 'execution_model_id' => 'deepseek-v4-flash', 'priority' => 0, 'is_enabled' => true],
                ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/deepseek-v4-flash', 'priority' => 1, 'is_enabled' => false],
            ],
        ],

        // --- Meta (Llama): aggregator-only, no direct Meta API ------------
        [
            'slug' => 'llama-4-maverick',
            'display_name' => 'Llama 4 Maverick',
            'brand' => 'meta',
            'capabilities' => ['context_window' => 1000000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/llama4-maverick-instruct-basic', 'priority' => 0, 'is_enabled' => false],
                ['execution_provider' => 'together', 'execution_model_id' => 'meta-llama/Llama-4-Maverick-17B-128E-Instruct', 'priority' => 1, 'is_enabled' => false],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'meta-llama/llama-4-maverick', 'priority' => 2, 'is_enabled' => false],
            ],
        ],

        [
            'slug' => 'llama-3.1-405b',
            'display_name' => 'Llama 3.1 405B',
            'brand' => 'meta',
            'capabilities' => ['context_window' => 128000, 'vision' => false, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/llama-v3p1-405b-instruct', 'priority' => 0, 'is_enabled' => false],
                ['execution_provider' => 'together', 'execution_model_id' => 'meta-llama/Meta-Llama-3.1-405B-Instruct-Turbo', 'priority' => 1, 'is_enabled' => false],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'meta-llama/llama-3.1-405b-instruct', 'priority' => 2, 'is_enabled' => false],
            ],
        ],

        // --- Moonshot AI (Kimi): aggregator-only --------------------------
        [
            'slug' => 'kimi-k3',
            'display_name' => 'Kimi K3',
            'brand' => 'moonshot',
            'capabilities' => ['context_window' => 1000000, 'vision' => true, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/kimi-k3', 'priority' => 0, 'is_enabled' => false],
                ['execution_provider' => 'together', 'execution_model_id' => 'moonshotai/Kimi-K3', 'priority' => 1, 'is_enabled' => false],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'moonshotai/kimi-k3', 'priority' => 2, 'is_enabled' => false],
            ],
        ],

        // --- Zhipu (GLM): aggregator-only ----------------------------------
        [
            'slug' => 'glm-5.2',
            'display_name' => 'GLM-5.2',
            'brand' => 'zhipu',
            'capabilities' => ['context_window' => 1040000, 'vision' => false, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/glm-5p2', 'priority' => 0, 'is_enabled' => false],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'z-ai/glm-5.2', 'priority' => 1, 'is_enabled' => false],
            ],
        ],

        // --- MiniMax: aggregator-only ---------------------------------------
        [
            'slug' => 'minimax-m3',
            'display_name' => 'MiniMax M3',
            'brand' => 'minimax',
            'capabilities' => ['context_window' => 1000000, 'vision' => false, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/minimax-m3', 'priority' => 0, 'is_enabled' => false],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'minimax/minimax-m3', 'priority' => 1, 'is_enabled' => false],
            ],
        ],

        // --- Alibaba (Qwen): aggregator-only --------------------------------
        [
            'slug' => 'qwen3.6',
            'display_name' => 'Qwen3.6',
            'brand' => 'alibaba',
            'capabilities' => ['context_window' => 256000, 'vision' => false, 'tool_use' => true],
            'routes' => [
                ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/qwen3p6-235b-a22b-instruct', 'priority' => 0, 'is_enabled' => false],
                ['execution_provider' => 'openrouter', 'execution_model_id' => 'qwen/qwen3.6-235b-a22b', 'priority' => 1, 'is_enabled' => false],
            ],
        ],

        // Internal-only, never shown in the public model picker (see
        // `ModelCatalog::is_internal`/`ModelCatalogController::index()`).
        // Powers the workflow-builder chat (`ProcessWorkflowBuilderMessageJob`) and its assist helpers (`WorkflowBuilderAssistant`).
        [
            'slug' => 'workflow-builder-assistant',
            'display_name' => 'Workflow Builder Assistant',
            'brand' => 'internal',
            'capabilities' => ['tool_use' => true],
            'internal' => true,
            'routes' => [
                ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 1, 'is_enabled' => true],
                ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/llama-v3p1-70b-instruct', 'priority' => 0, 'is_enabled' => true],
            ],
        ],

        // Internal-only: the personal assistant's default model when its
        // owner hasn't picked one (`TurnRunner::modelFor()`). Operator-fixed:
        // change it with `model-catalog:add-route personal-assistant …`.
        // Mistral Small 4 (via xkiro) for now; Claude Sonnet 5 is seeded
        // disabled, ready to switch on once an Anthropic key is configured.
        [
            'slug' => 'personal-assistant',
            'display_name' => 'Personal Assistant',
            'brand' => 'internal',
            'capabilities' => ['tool_use' => true],
            'internal' => true,
            'routes' => [
                ['execution_provider' => 'xkiro', 'execution_model_id' => 'mistralai/mistral-small-2603', 'priority' => 0, 'is_enabled' => true],
                ['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-sonnet-5', 'priority' => 1, 'is_enabled' => false],
            ],
        ],
    ],

    /**
     * What the model picker says about each entry. Speed and cost tiers place
     * a model relative to the rest of this catalog (1 = cheapest), not against
     * outside benchmarks.
     *
     * @var array<string, array{description: string, strengths: array<int, string>, speed: string, cost_tier: int}>
     */
    'details' => [
        'claude-fable-5' => ['description' => 'Anthropic\'s top model for the hardest reasoning, research and long, careful work.', 'strengths' => ['Deep reasoning', 'Long documents', 'Careful writing'], 'speed' => 'thorough', 'cost_tier' => 3],
        'claude-opus-5' => ['description' => 'Anthropic\'s most capable everyday model for complex, multi-step tasks and agents.', 'strengths' => ['Complex tasks', 'Coding', 'Agents with many tools'], 'speed' => 'thorough', 'cost_tier' => 3],
        'claude-sonnet-5' => ['description' => 'A balanced Anthropic model: strong quality at a moderate cost; a good default for agents.', 'strengths' => ['All-round', 'Coding', 'Tool use'], 'speed' => 'balanced', 'cost_tier' => 2],
        'claude-haiku-4-5' => ['description' => 'Anthropic\'s fast, low-cost model for simple, high-volume work.', 'strengths' => ['Speed', 'Low cost', 'Classification'], 'speed' => 'fast', 'cost_tier' => 1],
        'gpt-5.6-sol' => ['description' => 'OpenAI\'s most capable GPT-5.6 tier for demanding reasoning and long context.', 'strengths' => ['Deep reasoning', 'Very long context', 'Coding'], 'speed' => 'thorough', 'cost_tier' => 3],
        'gpt-5.6-terra' => ['description' => 'The balanced GPT-5.6 tier for everyday agent work.', 'strengths' => ['All-round', 'Tool use', 'Writing'], 'speed' => 'balanced', 'cost_tier' => 2],
        'gpt-5.6-luna' => ['description' => 'The fast, lightweight GPT-5.6 tier for quick, low-cost tasks.', 'strengths' => ['Speed', 'Low cost', 'Summaries'], 'speed' => 'fast', 'cost_tier' => 1],
        'gpt-4o' => ['description' => 'An established OpenAI multimodal model that handles text and images.', 'strengths' => ['Images', 'All-round', 'Reliable'], 'speed' => 'balanced', 'cost_tier' => 2],
        'gpt-4o-mini' => ['description' => 'A small, very cheap OpenAI model for simple tasks at volume.', 'strengths' => ['Low cost', 'Speed', 'Simple tasks'], 'speed' => 'fast', 'cost_tier' => 1],
        'gemini-3.1-pro' => ['description' => 'Google\'s capable Gemini model with a very long context window.', 'strengths' => ['Very long context', 'Images', 'Research'], 'speed' => 'thorough', 'cost_tier' => 2],
        'gemini-3.5-flash' => ['description' => 'A fast Gemini model with a long context window at low cost.', 'strengths' => ['Speed', 'Long context', 'Low cost'], 'speed' => 'fast', 'cost_tier' => 1],
        'grok-4.6' => ['description' => 'xAI\'s Grok model for reasoning and conversational tasks.', 'strengths' => ['Reasoning', 'Conversation', 'Images'], 'speed' => 'balanced', 'cost_tier' => 2],
        'mistral-medium-3.5' => ['description' => 'Mistral\'s mid-size model for solid quality at a moderate cost.', 'strengths' => ['All-round', 'Multilingual', 'Tool use'], 'speed' => 'balanced', 'cost_tier' => 2],
        'mistral-small-4' => ['description' => 'Mistral\'s small, fast model: inexpensive and good for straightforward agents.', 'strengths' => ['Speed', 'Low cost', 'Multilingual'], 'speed' => 'fast', 'cost_tier' => 1],
        'deepseek-v4-pro' => ['description' => 'DeepSeek\'s strong reasoning and coding model with a very long context.', 'strengths' => ['Reasoning', 'Coding', 'Very long context'], 'speed' => 'thorough', 'cost_tier' => 2],
        'deepseek-v4-flash' => ['description' => 'A fast, low-cost DeepSeek model for everyday tasks.', 'strengths' => ['Speed', 'Low cost', 'Coding'], 'speed' => 'fast', 'cost_tier' => 1],
        'llama-4-maverick' => ['description' => 'Meta\'s open-weight Llama 4 model with image understanding.', 'strengths' => ['Open weights', 'Images', 'Long context'], 'speed' => 'balanced', 'cost_tier' => 1],
        'llama-3.1-405b' => ['description' => 'Meta\'s large open-weight Llama 3.1 model.', 'strengths' => ['Open weights', 'All-round'], 'speed' => 'thorough', 'cost_tier' => 2],
        'kimi-k3' => ['description' => 'Moonshot\'s Kimi model with a very long context window.', 'strengths' => ['Very long context', 'Images', 'Agents'], 'speed' => 'balanced', 'cost_tier' => 2],
        'glm-5.2' => ['description' => 'Zhipu\'s GLM model for reasoning and tool use.', 'strengths' => ['Reasoning', 'Tool use', 'Long context'], 'speed' => 'balanced', 'cost_tier' => 1],
        'minimax-m3' => ['description' => 'MiniMax\'s long-context model for agents and documents.', 'strengths' => ['Long context', 'Agents'], 'speed' => 'balanced', 'cost_tier' => 1],
        'qwen3.6' => ['description' => 'Alibaba\'s Qwen model: capable and inexpensive.', 'strengths' => ['Low cost', 'Multilingual', 'Coding'], 'speed' => 'balanced', 'cost_tier' => 1],
    ],
];
