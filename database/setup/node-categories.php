<?php

use App\Enums\NodeCategory;

/**
 * The core, built-in node categories: one entry per `Enums\NodeCategory` case.
 */
return [
    [
        'enum' => NodeCategory::TriggersEvents,
        'icon' => 'zap',
        'color' => '#F59E0B',
        'description' => 'Start a workflow manually, on a schedule, or from a webhook/app event.',
        'sort_order' => 1,
    ],
    [
        'enum' => NodeCategory::InputsOutputs,
        'icon' => 'login-square-01',
        'color' => '#0EA5E9',
        'description' => 'Ask for values when a workflow runs, and choose what it returns.',
        'sort_order' => 2,
    ],
    [
        'enum' => NodeCategory::AiAutomation,
        'icon' => 'sparkles',
        'color' => '#8B5CF6',
        'description' => 'Prompt an LLM, extract structured data, or generate media.',
        'sort_order' => 3,
    ],
    [
        'enum' => NodeCategory::FlowLogic,
        'icon' => 'git-branch',
        'color' => '#3B82F6',
        'description' => 'Branch, loop, merge, wait, and handle errors.',
        'sort_order' => 4,
    ],
    [
        'enum' => NodeCategory::DataTransform,
        'icon' => 'shuffle',
        'color' => '#10B981',
        'description' => 'Reshape data, call generic APIs, run whitelisted transforms.',
        'sort_order' => 5,
    ],
    [
        'enum' => NodeCategory::Custom,
        'icon' => 'puzzle',
        'color' => '#6B7280',
        'description' => 'Nodes you define yourself, scoped to your workspace.',
        'sort_order' => 6,
    ],
];
