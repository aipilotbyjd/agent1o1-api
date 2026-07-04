<?php

namespace App\Engine\Nodes\Apps\AwsS3;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class AwsS3Node extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $disk = $this->buildDisk($input);

        return match ($operation) {
            'list_objects' => $this->success(['files' => $disk->files($input->config['prefix'] ?? '')]),
            'get_object' => $this->getObject($disk, $input),
            'put_object' => $this->putObject($disk, $input),
            'delete_object' => $this->deleteObject($disk, $input),
            'get_url' => $this->success(['url' => $disk->url($input->config['key'])]),
            default => $this->fail("AwsS3: unknown operation '{$operation}'"),
        };
    }

    private function buildDisk(NodeInput $input): Filesystem
    {
        return Storage::build([
            'driver' => 's3',
            'key' => $input->credentials['access_key_id'] ?? '',
            'secret' => $input->credentials['secret_access_key'] ?? '',
            'region' => $input->credentials['region'] ?? 'us-east-1',
            'bucket' => $input->config['bucket'] ?? ($input->credentials['bucket'] ?? ''),
        ]);
    }

    private function getObject($disk, NodeInput $input): NodeResult
    {
        $key = $input->config['key'];

        if (! $disk->exists($key)) {
            return $this->fail("S3 object not found: {$key}");
        }

        return $this->success(['content' => $disk->get($key), 'key' => $key]);
    }

    private function putObject($disk, NodeInput $input): NodeResult
    {
        $key = $input->config['key'];
        $disk->put($key, $input->config['content'] ?? '');

        return $this->success(['key' => $key, 'stored' => true]);
    }

    private function deleteObject($disk, NodeInput $input): NodeResult
    {
        $disk->delete($input->config['key']);

        return $this->success(['deleted' => true]);
    }
}
