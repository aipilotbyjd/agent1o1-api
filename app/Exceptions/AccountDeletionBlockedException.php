<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when an account cannot be deleted yet because it owns a workspace
 * that would be left without an owner (other people still belong to it) or
 * that still has a subscription billing it.
 */
class AccountDeletionBlockedException extends RuntimeException
{
    /**
     * @param  array<int, array{id: string, name: string, reasons: array<int, string>}>  $workspaces
     */
    public function __construct(public readonly array $workspaces)
    {
        parent::__construct('Before deleting your account, resolve the workspaces you own: transfer or delete any shared workspace and cancel any active subscription.');
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 409, ['workspaces' => $this->workspaces]);
    }
}
