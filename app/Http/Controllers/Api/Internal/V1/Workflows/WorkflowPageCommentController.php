<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Events\Pages\PageActivity;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowPageCommentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageComment;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;

/**
 * Comments on a page in the builder: see `WorkflowPageComment`. Anyone who
 * can open the page can read and add them; a comment is edited or deleted by
 * whoever wrote it, or by someone who manages pages, and so is a thread
 * resolved.
 */
class WorkflowPageCommentController extends Controller
{
    public function index(Workspace $workspace, WorkflowPage $page)
    {
        $this->authorizePage($workspace, $page);

        $threads = $page->comments()
            ->whereNull('parent_id')
            ->with(['author:id,name', 'resolver:id,name', 'replies.author:id,name'])
            ->oldest()
            ->get();

        return ApiResponse::success(['comments' => WorkflowPageCommentResource::collection($threads)]);
    }

    public function store(Request $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->authorizePage($workspace, $page);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'block_id' => ['nullable', 'string', 'max:64'],
            'parent_id' => ['nullable', 'uuid'],
        ]);

        $parent = null;

        if (filled($validated['parent_id'] ?? null)) {
            $parent = $page->comments()->whereNull('parent_id')->findOrFail($validated['parent_id']);
        }

        $comment = $page->comments()->create([
            'parent_id' => $parent?->id,
            // A reply is about whatever its thread is about.
            'block_id' => $parent === null ? ($validated['block_id'] ?? null) : $parent->block_id,
            'user_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);

        PageActivity::dispatch($page, 'comments', ['thread_id' => $parent?->id ?? $comment->id]);

        return ApiResponse::created(['comment' => WorkflowPageCommentResource::make($comment->load('author:id,name'))]);
    }

    /**
     * Edits the text, or resolves or reopens a thread.
     */
    public function update(Request $request, Workspace $workspace, WorkflowPage $page, WorkflowPageComment $comment)
    {
        $this->authorizePage($workspace, $page);
        abort_if($comment->workflow_page_id !== $page->id, 404);

        $validated = $request->validate([
            'body' => ['sometimes', 'required', 'string', 'max:5000'],
            'resolved' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('body', $validated)) {
            abort_if($comment->user_id !== $request->user()->id, 403, 'You can only edit your own comments.');
            $comment->body = trim($validated['body']);
        }

        if (array_key_exists('resolved', $validated)) {
            abort_if($comment->parent_id !== null, 422, 'Resolve the thread, not a reply.');
            $this->ensureMayModerate($request, $comment);
            $comment->forceFill($validated['resolved']
                ? ['resolved_at' => now(), 'resolved_by' => $request->user()->id]
                : ['resolved_at' => null, 'resolved_by' => null]);
        }

        $comment->save();

        PageActivity::dispatch($page, 'comments', ['thread_id' => $comment->parent_id ?? $comment->id]);

        return ApiResponse::success(['comment' => WorkflowPageCommentResource::make($comment->load(['author:id,name', 'resolver:id,name', 'replies.author:id,name']))]);
    }

    public function destroy(Request $request, Workspace $workspace, WorkflowPage $page, WorkflowPageComment $comment)
    {
        $this->authorizePage($workspace, $page);
        abort_if($comment->workflow_page_id !== $page->id, 404);
        $this->ensureMayModerate($request, $comment);

        $threadId = $comment->parent_id ?? $comment->id;
        $comment->delete();

        PageActivity::dispatch($page, 'comments', ['thread_id' => $threadId]);

        return ApiResponse::noContent();
    }

    private function authorizePage(Workspace $workspace, WorkflowPage $page): void
    {
        $this->requirePermission(Permission::PageView);
        $this->ensureBelongsToWorkspace($workspace, $page);
    }

    private function ensureMayModerate(Request $request, WorkflowPageComment $comment): void
    {
        if ($comment->user_id === $request->user()->id) {
            return;
        }

        $this->requirePermission(Permission::PageManage);
    }
}
