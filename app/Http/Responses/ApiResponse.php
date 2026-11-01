<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Success', int $status = HttpResponse::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'statusCode' => $status,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return self::success($data, $message, HttpResponse::HTTP_CREATED);
    }

    /**
     * Flattens a paginated resource collection so `meta` sits alongside `data`
     * rather than nested inside it, matching the frontend's expected shape.
     */
    public static function paginated(AnonymousResourceCollection $resource, string $message = 'Success'): JsonResponse
    {
        $paginator = $resource->resource;

        abort_unless($paginator instanceof LengthAwarePaginator, 500, 'ApiResponse::paginated() requires a paginated resource collection.');

        return response()->json([
            'success' => true,
            'statusCode' => HttpResponse::HTTP_OK,
            'message' => $message,
            'data' => $resource->collection,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], HttpResponse::HTTP_OK);
    }

    /**
     * The cursor-paginated counterpart of `paginated()`, for collections that
     * can't be counted or offset: Stripe's invoice list being the case that
     * introduced it. There is no `total` or `last_page` because the upstream
     * API doesn't report one; the client walks the cursors instead.
     */
    public static function cursorPaginated(AnonymousResourceCollection $resource, string $message = 'Success'): JsonResponse
    {
        $paginator = $resource->resource;

        abort_unless($paginator instanceof CursorPaginator, 500, 'ApiResponse::cursorPaginated() requires a cursor-paginated resource collection.');

        return response()->json([
            'success' => true,
            'statusCode' => HttpResponse::HTTP_OK,
            'message' => $message,
            'data' => $resource->collection,
            'meta' => [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], HttpResponse::HTTP_OK);
    }

    public static function noContent(): HttpResponse
    {
        return response()->noContent();
    }

    public static function error(string $message = 'Something went wrong', int $status = HttpResponse::HTTP_BAD_REQUEST, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'statusCode' => $status,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    public static function validationError(mixed $errors, string $message = 'The given data was invalid'): JsonResponse
    {
        return self::error($message, HttpResponse::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    public static function unauthorized(string $message = 'Unauthenticated'): JsonResponse
    {
        return self::error($message, HttpResponse::HTTP_UNAUTHORIZED);
    }

    public static function forbidden(string $message = 'This action is unauthorized'): JsonResponse
    {
        return self::error($message, HttpResponse::HTTP_FORBIDDEN);
    }

    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::error($message, HttpResponse::HTTP_NOT_FOUND);
    }

    public static function tooManyRequests(string $message = 'Too many requests'): JsonResponse
    {
        return self::error($message, HttpResponse::HTTP_TOO_MANY_REQUESTS);
    }

    public static function serverError(string $message = 'Server error'): JsonResponse
    {
        return self::error($message, HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}
