<?php

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\DB;

it('rewrites the editor\'s old node references to the form the engine resolves', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'fetch', 'type' => 'run_code', 'config' => ['operations' => []]],
            ['key' => 'post', 'type' => 'run_code', 'config' => ['operations' => [
                ['op' => 'set', 'output' => 'all', 'value' => '{{fetch.output}}'],
                ['op' => 'set', 'output' => 'city', 'value' => 'In {{ fetch.output.address.city }} at {{fetch.output.items[0].time}}'],
                ['op' => 'set', 'output' => 'kept', 'value' => '{{ input.output }} {{ other.output.x }} {{ nodes.fetch.output }}'],
            ]]],
        ],
        'edges' => [['from' => 'fetch', 'to' => 'post']],
    ]);
    $workflow->publishVersion(publisher: $owner);

    (require database_path('migrations/2026_10_08_100000_rewrite_editor_node_output_templates.php'))->up();

    $expected = [
        ['op' => 'set', 'output' => 'all', 'value' => '{{nodes.fetch}}'],
        ['op' => 'set', 'output' => 'city', 'value' => 'In {{nodes.fetch.address.city}} at {{nodes.fetch.items[0].time}}'],
        ['op' => 'set', 'output' => 'kept', 'value' => '{{ input.output }} {{ other.output.x }} {{ nodes.fetch.output }}'],
    ];

    $node = json_decode(DB::table('workflow_nodes')->where('workflow_id', $workflow->id)->where('key', 'post')->value('config'), true);
    $graph = json_decode(DB::table('workflow_versions')->where('workflow_id', $workflow->id)->value('graph'), true);

    expect($node['operations'])->toBe($expected);
    expect(collect($graph['nodes'])->firstWhere('key', 'post')['config']['operations'])->toBe($expected);
});
