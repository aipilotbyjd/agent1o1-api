<?php

namespace App\Traits;

use App\Http\Response\ApiResponse as ResponseBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    protected function successResponse(string $message, array|JsonResource|null $data = null, int $statusCode = 200): JsonResponse
    {
        $response = ResponseBuilder::success($message);

        if ($data !== null) {
            $response->data($data);
        }

        return $response->send($statusCode);
    }

    protected function paginatedResponse(string $message, ResourceCollection $collection): JsonResponse
    {
        return ResponseBuilder::success($message)
            ->paginate($collection)
            ->send();
    }

    protected function errorResponse(string $message, int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = ResponseBuilder::error($message);

        if ($errors !== null) {
            $response->errors($errors);
        }

        return $response->send($statusCode);
    }
}
