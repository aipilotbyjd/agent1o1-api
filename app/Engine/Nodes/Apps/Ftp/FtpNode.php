<?php

namespace App\Engine\Nodes\Apps\Ftp;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Storage;

class FtpNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $disk = Storage::build([
            'driver' => 'ftp',
            'host' => $input->credentials['host'] ?? '',
            'username' => $input->credentials['username'] ?? '',
            'password' => $input->credentials['password'] ?? '',
            'port' => (int) ($input->credentials['port'] ?? 21),
        ]);

        return match ($operation) {
            'list_files' => $this->success(['files' => $disk->files($input->config['path'] ?? '')]),
            'download' => $this->success(['content' => $disk->get($input->config['path'])]),
            'upload' => $this->upload($disk, $input),
            'delete' => $this->delete($disk, $input),
            default => $this->fail("Ftp: unknown operation '{$operation}'"),
        };
    }

    private function upload($disk, NodeInput $input): NodeResult
    {
        $disk->put($input->config['path'], $input->config['content'] ?? '');

        return $this->success(['uploaded' => true, 'path' => $input->config['path']]);
    }

    private function delete($disk, NodeInput $input): NodeResult
    {
        $disk->delete($input->config['path']);

        return $this->success(['deleted' => true]);
    }
}
