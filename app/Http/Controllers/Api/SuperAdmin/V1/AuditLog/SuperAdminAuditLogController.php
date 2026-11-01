<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\AuditLog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SuperAdmin\V1\AuditLog\SuperAdminAuditLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\SuperAdmin\Audit\AuditChainVerifier;
use Illuminate\Http\Request;

class SuperAdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = SuperAdminAuditLog::query()
            ->with('admin:id,name,email')
            ->when($request->filled('actor'), fn ($query) => $query->where('super_admin_id', $request->string('actor')))
            ->when($request->filled('action'), fn ($query) => $query->whereLike('action', $request->string('action').'%'))
            ->when($request->filled('subject_type'), fn ($query) => $query->where('subject_type', $request->string('subject_type')))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->string('subject_id')))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', $request->date('to')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(min($request->integer('per_page', 50), 100));

        return ApiResponse::cursorPaginated(SuperAdminAuditLogResource::collection($logs));
    }

    /**
     * Checks the whole log is still the log that was written: nothing
     * removed, changed or slipped in. Pass `expected_head` (a `head_hash` you
     * kept elsewhere) to also catch entries cut off the end.
     */
    public function integrity(Request $request, AuditChainVerifier $verifier)
    {
        $request->validate(['expected_head' => ['nullable', 'string', 'size:64']]);

        return ApiResponse::success($verifier->verify($request->input('expected_head')));
    }

    public function show(SuperAdminAuditLog $log)
    {
        return ApiResponse::success((new SuperAdminAuditLogResource($log->load('admin:id,name,email')))->resolve());
    }
}
