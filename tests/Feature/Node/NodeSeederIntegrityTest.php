<?php

use App\Engine\NodeCatalog;
use App\Models\Node;
use Database\Seeders\NodeCategorySeeder;
use Database\Seeders\NodeSeeder;

beforeEach(function () {
    $this->seed(NodeCategorySeeder::class);
    $this->seed(NodeSeeder::class);
});

/**
 * Palette nodes that are advertised in the catalog but have no handler wired
 * up yet. They fail at execution time. This baseline is intentionally explicit
 * so that (a) fixing one forces an update here, and (b) no NEW unresolved type
 * slips in unnoticed — which is exactly how the Discord node (type
 * "comm.discord_message", whose "comm" slug resolved to nothing) shipped broken.
 *
 * @var list<string>
 */
const KNOWN_UNIMPLEMENTED_NODE_TYPES = [
    'ai.embeddings',
    'ai.image_generation',
    'ai.sentiment',
    'ai.summarizer',
    'ai.text_classifier',
    'data.aggregate',
    'flow.batch_processor',
    'flow.wait_for_event',
    'http.graphql',
    'http.webhook_response',
    'storage.read_file',
    'storage.write_file',
    'util.error_handler',
];

test('every seeded node type resolves to a handler, except the known-unimplemented baseline', function () {
    $unresolved = Node::query()
        ->pluck('type')
        ->reject(fn (string $type) => NodeCatalog::resolve($type) !== null)
        ->sort()
        ->values()
        ->all();

    $expected = collect(KNOWN_UNIMPLEMENTED_NODE_TYPES)->sort()->values()->all();

    expect($unresolved)->toBe(
        $expected,
        'The set of unresolved node types changed. If you implemented or removed one, '.
        'update KNOWN_UNIMPLEMENTED_NODE_TYPES; if a new unresolved type appeared, wire up its handler.'
    );
});

test('no third-party integration (app) node is left unresolved', function () {
    $unresolved = Node::all()
        ->filter(fn (Node $node) => NodeCatalog::isAppNode($node->type))
        ->reject(fn (Node $node) => NodeCatalog::resolve($node->type) !== null)
        ->pluck('type')
        ->values()
        ->all();

    expect($unresolved)->toBe([]);
});

test('every app node exposes an operation field whose default is one of its enum values', function () {
    /**
     * App nodes dispatch on config.operation, so the config schema must both
     * declare that field and default it to a value listed in its own enum.
     * A default that is absent from the enum can never run the node.
     */
    $offenders = [];

    foreach (Node::all() as $node) {
        if (! NodeCatalog::isAppNode($node->type)) {
            continue;
        }

        $operation = $node->config_schema['properties']['operation'] ?? null;

        if ($operation === null) {
            $offenders[] = "{$node->type}: missing operation field";

            continue;
        }

        $enum = $operation['enum'] ?? [];
        $default = $operation['default'] ?? null;

        if ($default !== null && ! in_array($default, $enum, true)) {
            $offenders[] = "{$node->type}: default '{$default}' not in enum";
        }
    }

    expect($offenders)->toBe([]);
});
