<?php

namespace App\Http\Response;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Unified JSON response builder for all API endpoints.
 *
 * @example Success:   {"success":true,"statusCode":200,"message":"...","data":{...}}
 * @example Paginated: {"success":true,"statusCode":200,"message":"...","data":[...],"pagination":{...}}
 * @example Error:     {"success":false,"statusCode":422,"message":"...","errors":{...}}
 */
final class ApiResponse
{
    private bool $success;

    private string $message;

    private int $statusCode;

    private mixed $data = null;

    private bool $hasData = false;

    private mixed $errors = null;

    private array $pagination = [];

    private function __construct(bool $success, string $message, int $statusCode)
    {
        $this->success = $success;
        $this->message = $message;
        $this->statusCode = $statusCode;
    }

    // ─── Factory Methods ─────────────────────────────────────

    public static function success(string $message = 'Success.'): self
    {
        return new self(true, $message, 200);
    }

    public static function error(string $message = 'An error occurred.'): self
    {
        return new self(false, $message, 400);
    }

    // ─── Builder Methods ─────────────────────────────────────

    public function data(mixed $data): self
    {
        $this->data = $data;
        $this->hasData = true;

        return $this;
    }

    public function errors(mixed $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function status(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function paginate(ResourceCollection $collection): self
    {
        $paginated = $collection->response()->getData(true);

        $this->data = $paginated['data'];
        $this->hasData = true;

        if (isset($paginated['meta'])) {
            $meta = $paginated['meta'];
            $this->pagination = [
                'total' => $meta['total'] ?? 0,
                'per_page' => $meta['per_page'] ?? 15,
                'current_page' => $meta['current_page'] ?? 1,
                'last_page' => $meta['last_page'] ?? 1,
                'from' => $meta['from'] ?? null,
                'to' => $meta['to'] ?? null,
            ];
        }

        return $this;
    }

    // ─── Terminal Method ─────────────────────────────────────

    public function send(?int $statusCode = null): JsonResponse
    {
        if ($statusCode !== null) {
            $this->statusCode = $statusCode;
        }

        $body = [
            'success' => $this->success,
            'statusCode' => $this->statusCode,
            'message' => $this->message,
        ];

        if ($this->hasData) {
            $body['data'] = $this->data;
        }

        if ($this->errors !== null) {
            $body['errors'] = $this->errors;
        }

        if (! empty($this->pagination)) {
            $body['pagination'] = $this->pagination;
        }

        return response()->json($body, $this->statusCode);
    }

    // ─── Success Shortcuts ───────────────────────────────────

    public static function created(string $message, mixed $data = null): JsonResponse
    {
        $response = self::success($message);
        if ($data !== null) {
            $response->data($data);
        }

        return $response->send(201);
    }

    public static function accepted(string $message, mixed $data = null): JsonResponse
    {
        $response = self::success($message);
        if ($data !== null) {
            $response->data($data);
        }

        return $response->send(202);
    }

    // ─── Error Shortcuts ─────────────────────────────────────

    public static function unauthorized(string $message = 'Unauthenticated.'): JsonResponse
    {
        return self::error($message)->send(401);
    }

    public static function forbidden(string $message = 'Forbidden.'): JsonResponse
    {
        return self::error($message)->send(403);
    }

    public static function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return self::error($message)->send(404);
    }

    public static function validationFailed(array $errors, string $message = 'Validation failed.'): JsonResponse
    {
        return self::error($message)->errors($errors)->send(422);
    }

    public static function tooManyRequests(string $message = 'Too many requests.'): JsonResponse
    {
        return self::error($message)->send(429);
    }

    public static function serverError(string $message = 'Internal server error.'): JsonResponse
    {
        return self::error($message)->send(500);
    }
}
