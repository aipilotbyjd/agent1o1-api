<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TemplateCollectionResource;
use App\Models\TemplateCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateCollectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $collections = TemplateCollection::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->successResponse('Template collections retrieved.', TemplateCollectionResource::collection($collections));
    }

    public function show(Request $request, TemplateCollection $templateCollection): JsonResponse
    {
        return $this->successResponse('Template collection retrieved.', new TemplateCollectionResource($templateCollection));
    }
}
