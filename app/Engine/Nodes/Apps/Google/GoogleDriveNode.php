<?php

namespace App\Engine\Nodes\Apps\Google;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class GoogleDriveNode extends AppNode
{
    public const TYPE = 'google_drive';

    private const BASE_URL = 'https://www.googleapis.com/drive/v3';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'list_files' => $this->listFiles($input),
            'get_file' => $this->getFile($input),
            'download_file' => $this->downloadFile($input),
            'upload_file' => $this->uploadFile($input),
            'update_file' => $this->updateFile($input),
            'create_folder' => $this->createFolder($input),
            'delete_file' => $this->deleteFile($input),
            'share_file' => $this->shareFile($input),
            default => $this->fail("GoogleDrive: unknown operation '{$operation}'"),
        };
    }

    private function listFiles(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get('/files', [
                'q' => $input->config['query'] ?? '',
                'pageSize' => $input->config['page_size'] ?? 10,
                'fields' => 'files(id,name,mimeType,modifiedTime,size)',
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleDrive list_files failed: {$response->body()}");
    }

    private function getFile(NodeInput $input): NodeResult
    {
        $fileId = $input->config['file_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/files/{$fileId}", ['fields' => '*']);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleDrive get_file failed: {$response->body()}");
    }

    private function downloadFile(NodeInput $input): NodeResult
    {
        $fileId = $input->config['file_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/files/{$fileId}?alt=media");

        return $response->successful()
            ? $this->success(['content' => $response->body(), 'file_id' => $fileId])
            : $this->fail("GoogleDrive download_file failed: {$response->body()}");
    }

    private function uploadFile(NodeInput $input): NodeResult
    {
        $name = $input->config['name'];
        $content = $input->config['content'];
        $mimeType = $input->config['mime_type'] ?? 'text/plain';
        $parentId = $input->config['parent_id'] ?? null;

        $metadata = ['name' => $name];
        if ($parentId) {
            $metadata['parents'] = [$parentId];
        }

        $response = $this->httpWithAuth($input)
            ->withBody(
                "--boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n".
                json_encode($metadata).
                "\r\n--boundary\r\nContent-Type: {$mimeType}\r\n\r\n".
                $content.
                "\r\n--boundary--",
                'multipart/related; boundary=boundary'
            )
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleDrive upload_file failed: {$response->body()}");
    }

    private function updateFile(NodeInput $input): NodeResult
    {
        $fileId = $input->config['file_id'];
        $metadata = array_filter([
            'name' => $input->config['name'] ?? null,
        ]);

        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->patch("/files/{$fileId}", $metadata);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleDrive update_file failed: {$response->body()}");
    }

    private function createFolder(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/files', [
                'name' => $input->config['name'],
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => isset($input->config['parent_id']) ? [$input->config['parent_id']] : [],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleDrive create_folder failed: {$response->body()}");
    }

    private function deleteFile(NodeInput $input): NodeResult
    {
        $fileId = $input->config['file_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)->delete("/files/{$fileId}");

        return $response->successful()
            ? $this->success(['deleted' => true, 'file_id' => $fileId])
            : $this->fail("GoogleDrive delete_file failed: {$response->body()}");
    }

    private function shareFile(NodeInput $input): NodeResult
    {
        $fileId = $input->config['file_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/files/{$fileId}/permissions", [
                'type' => $input->config['type'] ?? 'user',
                'role' => $input->config['role'] ?? 'reader',
                'emailAddress' => $input->config['email'] ?? null,
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("GoogleDrive share_file failed: {$response->body()}");
    }
}
