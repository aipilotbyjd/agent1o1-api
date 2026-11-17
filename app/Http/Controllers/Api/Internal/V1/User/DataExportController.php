<?php

namespace App\Http\Controllers\Api\Internal\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\User\DataExportResource;
use App\Http\Responses\ApiResponse;
use App\Models\Account\DataExport;
use App\Services\Account\DataExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The signed-in person's own "download my data" exports. Every lookup is
 * scoped through the user's relation, so one account can never see or
 * fetch another's archive.
 */
class DataExportController extends Controller
{
    public function __construct(private readonly DataExportService $exports) {}

    public function index(Request $request): JsonResponse
    {
        $exports = $request->user()->dataExports()->latest()->limit(10)->get();

        return ApiResponse::success(['exports' => DataExportResource::collection($exports)]);
    }

    public function store(Request $request): JsonResponse
    {
        $export = $this->exports->request($request->user());

        return ApiResponse::success(
            ['export' => DataExportResource::make($export)],
            "We're preparing your data export. We'll email you when it's ready to download.",
            202,
        );
    }

    public function download(Request $request, string $dataExport): StreamedResponse|JsonResponse
    {
        /** @var DataExport $export */
        $export = $request->user()->dataExports()->findOrFail($dataExport);

        if (! $export->isDownloadable()) {
            return ApiResponse::error('This export is not ready, or its download has expired. Request a new one.', 410);
        }

        return Storage::disk($export->disk)->download($export->path, $export->fileName(), [
            'Content-Type' => 'application/zip',
        ]);
    }
}
