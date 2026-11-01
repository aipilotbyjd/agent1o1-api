<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveCopyFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveCreateFolderNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveDeleteFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveGetFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveListFilesNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveListUpdatedFilesNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveMoveFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveRenameFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveShareFileNode;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveUploadFileNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

it('registers every google drive node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'google_drive_list_files' => GoogleDriveListFilesNode::class,
        'google_drive_get_file' => GoogleDriveGetFileNode::class,
        'google_drive_delete_file' => GoogleDriveDeleteFileNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('google_drive');
    }
});

it('lists files with the query and page_size as query params', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['files' => []])]);

    $node = new GoogleDriveListFilesNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'ya29-test', 'query' => "name contains 'report'", 'page_size' => 5], []);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/drive/v3/files')
            && $request['q'] === "(name contains 'report') and trashed = false"
            && $request['pageSize'] == 5;
    });
});

it('gets a file by id', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['id' => 'f1', 'name' => 'report.pdf'])]);

    $node = new GoogleDriveGetFileNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'file_id' => 'f1'], []);

    expect($output)->toBe(['id' => 'f1', 'name' => 'report.pdf']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/drive/v3/files/f1'));
});

it('deletes a file by id', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response('', 204)]);

    $node = new GoogleDriveDeleteFileNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'file_id' => 'f1'], []);

    expect($output)->toBe([]);
    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE' && str_contains($request->url(), '/drive/v3/files/f1');
    });
});

it('throws when drive answers a non-2xx status', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['error' => ['message' => 'File not found']], 404)]);

    $node = new GoogleDriveGetFileNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['access_token' => 'ya29-test', 'file_id' => 'missing'], []))
        ->toThrow(RuntimeException::class, 'File not found');
});

it('throws when access_token is missing', function () {
    $node = new GoogleDriveListFilesNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, [], []))->toThrow(RuntimeException::class, 'access_token');
});

const DRIVE_BASE = ['access_token' => 'ya29-test'];

it('registers the drive write nodes', function () {
    foreach (['create_folder', 'upload_file', 'copy_file', 'move_file', 'rename_file', 'share_file'] as $suffix) {
        expect(app(NodeRegistry::class)->has("google_drive_{$suffix}"))->toBeTrue();
    }
});

it('creates a folder inside a parent folder', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['id' => 'f1', 'name' => 'Invoices'])]);

    $output = (new GoogleDriveCreateFolderNode)->execute(Run::factory()->create(), [...DRIVE_BASE, 'name' => 'Invoices', 'parent_id' => 'root1'], []);

    expect($output['id'])->toBe('f1');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/drive/v3/files?supportsAllDrives=true')
        && $request['mimeType'] === 'application/vnd.google-apps.folder'
        && $request['parents'] === ['root1']);
});

it('creates a folder in My Drive when no parent is set', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['id' => 'f2'])]);

    (new GoogleDriveCreateFolderNode)->execute(Run::factory()->create(), [...DRIVE_BASE, 'name' => 'Top'], []);

    Http::assertSent(fn ($request) => ! isset($request['parents']));
});

it('uploads text content as a multipart file with its metadata', function () {
    Http::fake(['www.googleapis.com/upload/*' => Http::response(['id' => 'u1', 'name' => 'a.csv'])]);

    $output = (new GoogleDriveUploadFileNode)->execute(Run::factory()->create(), [
        ...DRIVE_BASE, 'name' => 'a.csv', 'content' => "x,y\n1,2", 'mime_type' => 'text/csv', 'parent_id' => 'dir1',
    ], []);

    expect($output['id'])->toBe('u1');
    Http::assertSent(function ($request) {
        $body = $request->body();

        return str_contains($request->url(), '/upload/drive/v3/files?uploadType=multipart')
            && str_starts_with($request->header('Content-Type')[0], 'multipart/related; boundary=')
            && str_contains($body, '"name":"a.csv"')
            && str_contains($body, '"parents":["dir1"]')
            && str_contains($body, "Content-Type: text/csv\r\n\r\nx,y\n1,2");
    });
});

it('copies a file with no body fields as an empty json object', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['id' => 'c1'])]);

    (new GoogleDriveCopyFileNode)->execute(Run::factory()->create(), [...DRIVE_BASE, 'file_id' => 'src'], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/files/src/copy') && $request->body() === '{}');
});

it('copies a file under a new name into a folder', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['id' => 'c2'])]);

    (new GoogleDriveCopyFileNode)->execute(Run::factory()->create(), [...DRIVE_BASE, 'file_id' => 'src', 'name' => 'Contract', 'parent_id' => 'dir1'], []);

    Http::assertSent(fn ($request) => $request['name'] === 'Contract' && $request['parents'] === ['dir1']);
});

it('moves a file by swapping its current parents for the destination', function () {
    Http::fake([
        'www.googleapis.com/drive/v3/files/doc1?fields=*' => Http::response(['parents' => ['old1', 'old2']]),
        'www.googleapis.com/drive/*' => Http::response(['id' => 'doc1', 'parents' => ['new1']]),
    ]);

    (new GoogleDriveMoveFileNode)->execute(Run::factory()->create(), [...DRIVE_BASE, 'file_id' => 'doc1', 'destination_id' => 'new1'], []);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'addParents=new1')
        && str_contains($request->url(), 'removeParents=old1%2Cold2'));
});

it('renames a file and leaves the description alone when none is given', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['id' => 'doc1', 'name' => 'Final'])]);

    (new GoogleDriveRenameFileNode)->execute(Run::factory()->create(), [...DRIVE_BASE, 'file_id' => 'doc1', 'name' => 'Final'], []);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request->data() === ['name' => 'Final']);
});

it('shares a file with one person and emails them', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['id' => 'perm1'])]);

    (new GoogleDriveShareFileNode)->execute(Run::factory()->create(), [
        ...DRIVE_BASE, 'file_id' => 'doc1', 'type' => 'user', 'email' => 'ada@example.com', 'role' => 'writer', 'send_notification' => true, 'message' => 'Take a look',
    ], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/files/doc1/permissions?')
        && str_contains($request->url(), 'sendNotificationEmail=true')
        && str_contains($request->url(), 'emailMessage=Take+a+look')
        && $request->data() === ['type' => 'user', 'role' => 'writer', 'emailAddress' => 'ada@example.com']);
});

it('shares a file with anyone who has the link, without an email address or notification', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['id' => 'perm2'])]);

    (new GoogleDriveShareFileNode)->execute(Run::factory()->create(), [...DRIVE_BASE, 'file_id' => 'doc1', 'type' => 'anyone', 'role' => 'reader'], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendNotificationEmail=false')
        && $request->data() === ['type' => 'anyone', 'role' => 'reader']);
});

it('refuses to share with a person without an email address', function () {
    Http::fake();

    (new GoogleDriveShareFileNode)->execute(Run::factory()->create(), [...DRIVE_BASE, 'file_id' => 'doc1', 'type' => 'user', 'role' => 'reader'], []);
})->throws(InvalidArgumentException::class, 'enter the email address');

it('surfaces a drive api error message', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['error' => ['message' => 'File not found']], 404)]);

    (new GoogleDriveRenameFileNode)->execute(Run::factory()->create(), [...DRIVE_BASE, 'file_id' => 'nope', 'name' => 'x'], []);
})->throws(RuntimeException::class, 'File not found');

it('lists newest files first with the fields a flow needs, and leaves out the trash', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['files' => []])]);

    (new GoogleDriveListFilesNode)->execute(Run::factory()->create(), DRIVE_BASE, []);

    Http::assertSent(fn ($request) => $request['q'] === 'trashed = false'
        && $request['orderBy'] === 'createdTime desc'
        && str_contains($request['fields'], 'createdTime')
        && str_contains($request['fields'], 'webViewLink')
        && $request['supportsAllDrives'] === 'true');
});

it('narrows a file list to a folder and a type, grouping the users own query', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['files' => []])]);

    (new GoogleDriveListFilesNode)->execute(Run::factory()->create(), [
        ...DRIVE_BASE, 'query' => "name contains 'a' or name contains 'b'", 'folder_id' => "dir'1", 'file_type' => 'application/vnd.google-apps.spreadsheet',
    ], []);

    Http::assertSent(fn ($request) => $request['q'] === "(name contains 'a' or name contains 'b') and 'dir\\'1' in parents and mimeType = 'application/vnd.google-apps.spreadsheet' and trashed = false");
});

it('matches every image type with a prefix filter', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['files' => []])]);

    (new GoogleDriveListFilesNode)->execute(Run::factory()->create(), [...DRIVE_BASE, 'file_type' => 'image/*'], []);

    Http::assertSent(fn ($request) => str_contains($request['q'], "mimeType contains 'image/'"));
});

it('keeps trashed files out unless asked, and respects a query about the trash', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['files' => []])]);
    $node = new GoogleDriveListFilesNode;

    $node->execute(Run::factory()->create(), [...DRIVE_BASE, 'include_trashed' => true], []);
    $node->execute(Run::factory()->create(), [...DRIVE_BASE, 'query' => 'trashed = true'], []);

    Http::assertSent(fn ($request) => $request['q'] === '');
    Http::assertSent(fn ($request) => $request['q'] === 'trashed = true');
});

it('acts as a new file trigger that reads newest first and a wide page', function () {
    $node = new GoogleDriveListFilesNode;

    expect($node->triggerListsNewestFirst())->toBeTrue()
        ->and($node->triggerReadConfig(['page_size' => 5])['page_size'])->toBe(100)
        ->and($node->triggerItems(['files' => [['id' => 'f1'], 'junk']], []))->toHaveCount(1);
});

it('lists files edited recently, most recently changed first', function () {
    Http::fake(['www.googleapis.com/drive/*' => Http::response(['files' => []])]);

    (new GoogleDriveListUpdatedFilesNode)->execute(Run::factory()->create(), [...DRIVE_BASE, 'changed_within_minutes' => 30], []);

    Http::assertSent(fn ($request) => $request['orderBy'] === 'modifiedTime desc'
        && preg_match("/^modifiedTime > '[0-9T:+\\-.Z]+' and trashed = false$/", $request['q']) === 1);
});

it('triggers on edits, not on the creation of a file, and once per edit', function () {
    $node = new GoogleDriveListUpdatedFilesNode;

    $files = $node->triggerItems(['files' => [
        ['id' => 'new', 'createdTime' => '2026-01-01T10:00:00Z', 'modifiedTime' => '2026-01-01T10:00:05Z'],
        ['id' => 'edited', 'createdTime' => '2026-01-01T10:00:00Z', 'modifiedTime' => '2026-01-02T09:00:00Z'],
        ['id' => 'nodates'],
    ]], []);

    expect(array_column($files, 'id'))->toBe(['edited'])
        ->and($node->triggerItemId($files[0]))->toBe('edited:2026-01-02T09:00:00Z')
        ->and($node->triggerName())->toBe('Google Drive: File updated')
        ->and($node->triggerReadConfig([])['changed_within_minutes'])->toBe(360);
});
