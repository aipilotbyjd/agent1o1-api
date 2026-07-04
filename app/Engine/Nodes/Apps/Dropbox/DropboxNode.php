<?php

namespace App\Engine\Nodes\Apps\Dropbox;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class DropboxNode extends AppNode
{
    private const BASE_URL = 'https://api.dropboxapi.com/2';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'list_folder' => $this->listFolder($input),
            'create_folder' => $this->createFolder($input),
            'delete_file' => $this->deleteFile($input),
            'move_file' => $this->moveFile($input),
            'get_link' => $this->getLink($input),
            default => $this->fail("Dropbox: unknown operation '{$operation}'"),
        };
    }

    private function listFolder(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/files/list_folder', ['path' => $input->config['path'] ?? '']);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Dropbox list_folder failed: {$response->body()}");
    }

    private function createFolder(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/files/create_folder_v2', ['path' => $input->config['path'], 'autorename' => false]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Dropbox create_folder failed: {$response->body()}");
    }

    private function deleteFile(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/files/delete_v2', ['path' => $input->config['path']]);

        return $response->successful()
            ? $this->success(['deleted' => true])
            : $this->fail("Dropbox delete_file failed: {$response->body()}");
    }

    private function moveFile(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/files/move_v2', [
                'from_path' => $input->config['from_path'],
                'to_path' => $input->config['to_path'],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Dropbox move_file failed: {$response->body()}");
    }

    private function getLink(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/sharing/create_shared_link_with_settings', ['path' => $input->config['path']]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Dropbox get_link failed: {$response->body()}");
    }
}
