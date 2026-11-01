<?php

namespace App\Http\Controllers\Api\Internal\V1\Timezones;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Timezones\Timezones;
use Illuminate\Http\JsonResponse;

/**
 * The timezone picker's choices: see `Timezones`.
 */
class TimezoneController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'timezones' => Timezones::options(),
        ]);
    }
}
