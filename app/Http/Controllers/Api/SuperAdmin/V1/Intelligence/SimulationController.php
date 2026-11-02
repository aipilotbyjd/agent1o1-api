<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Intelligence;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Intelligence\AdminAnalyst;
use App\Services\Intelligence\PricingSimulator;
use App\Services\Intelligence\RoutingSimulator;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * What-if tools, and the analyst that answers questions in plain language.
 * None of them changes anything.
 */
class SimulationController extends Controller
{
    public function pricing(Request $request, PricingSimulator $simulator)
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'plans' => ['nullable', 'array'],
            'plans.*.price_monthly' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'plans.*.credits_monthly' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'overage_credit_value_usd' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        return ApiResponse::success($simulator->simulate($data, (int) ($data['days'] ?? 90)));
    }

    public function routing(Request $request, RoutingSimulator $simulator)
    {
        $data = $request->validate([
            'from_provider' => ['required', 'string', 'max:100'],
            'from_model' => ['required', 'string', 'max:200'],
            'to_provider' => ['required', 'string', 'max:100'],
            'to_model' => ['required', 'string', 'max:200'],
            'feature' => ['nullable', 'string', 'max:64'],
            'days' => ['nullable', 'integer', 'min:1', 'max:180'],
        ]);

        try {
            return ApiResponse::success($simulator->simulate($data['from_provider'], $data['from_model'], $data['to_provider'], $data['to_model'], $data['feature'] ?? null, (int) ($data['days'] ?? 30)));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function ask(Request $request, AdminAnalyst $analyst)
    {
        $data = $request->validate(['question' => ['required', 'string', 'min:5', 'max:2000']]);

        return ApiResponse::success($analyst->ask($request->user('super-admin'), $data['question']));
    }
}
