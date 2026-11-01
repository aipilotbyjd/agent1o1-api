<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Broadcasts;

use App\Enums\Broadcasts\BroadcastRecipientStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SuperAdmin\V1\Broadcasts\BroadcastRecipientResource;
use App\Http\Responses\ApiResponse;
use App\Models\Broadcasts\BroadcastCampaign;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Delivery status, person by person.
 */
class BroadcastRecipientController extends Controller
{
    public function index(Request $request, BroadcastCampaign $campaign)
    {
        $request->validate(['status' => ['nullable', Rule::enum(BroadcastRecipientStatus::class)]]);

        $recipients = $campaign->recipients()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), fn ($query) => $query->whereLike('email', '%'.$request->string('q').'%'))
            ->orderBy('email')
            ->orderBy('id')
            ->paginate(min($request->integer('per_page', 50), 200));

        return ApiResponse::paginated(BroadcastRecipientResource::collection($recipients));
    }
}
