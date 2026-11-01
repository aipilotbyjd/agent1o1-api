<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Customers;

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Customers\StoreNoteRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Customers\SyncTagsRequest;
use App\Http\Responses\ApiResponse;
use App\Models\SuperAdmin\SuperAdminAccountNote;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\SuperAdmin\Customers\CustomerAnnotations;
use App\Services\SuperAdmin\Customers\CustomerSubjects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Notes and tags on a user or a workspace. The route says which kind through
 * its `type` default, so one controller serves both.
 */
class CustomerAnnotationController extends Controller
{
    public function __construct(private readonly CustomerAnnotations $annotations) {}

    public function index(Request $request, string $id)
    {
        $subject = $this->subject($request, $id);

        return ApiResponse::success([
            'tags' => $this->annotations->tags($subject),
            'notes' => $this->annotations->notes($subject)->map(fn (SuperAdminAccountNote $note): array => $this->note($note))->all(),
        ]);
    }

    public function storeNote(StoreNoteRequest $request, string $id)
    {
        $note = $this->annotations->addNote($request->user('super-admin'), $this->subject($request, $id), $request->validated('body'));

        return ApiResponse::success($this->note($note), 'Note added.', 201);
    }

    public function destroyNote(Request $request, string $id, string $note)
    {
        $subject = $this->subject($request, $id);
        $admin = $request->user('super-admin');

        $record = SuperAdminAccountNote::query()
            ->where('subject_type', CustomerSubjects::label($subject))
            ->where('subject_id', $subject->getKey())
            ->findOrFail($note);

        // Anyone may take down their own note; an owner may take down any.
        abort_unless($record->super_admin_id === $admin->id || $admin->role === SuperAdminRole::Owner, 403, 'Only the author or an owner can delete this note.');

        $this->annotations->deleteNote($admin, $subject, $record);

        return ApiResponse::success(null, 'Note deleted.');
    }

    public function syncTags(SyncTagsRequest $request, string $id)
    {
        $tags = $this->annotations->syncTags($request->user('super-admin'), $this->subject($request, $id), $request->tags());

        return ApiResponse::success(['tags' => $tags], 'Tags saved.');
    }

    private function subject(Request $request, string $id): Model
    {
        return $request->route('type') === CustomerSubjects::USER
            ? User::query()->findOrFail($id)
            : Workspace::query()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function note(SuperAdminAccountNote $note): array
    {
        return [
            'id' => $note->id,
            'body' => $note->body,
            'author' => $note->author === null ? null : ['id' => $note->author->id, 'name' => $note->author->name, 'email' => $note->author->email],
            'created_at' => $note->created_at,
        ];
    }
}
