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
 * Delivery status, person by person, and whether they opened the notification.
 */
class BroadcastRecipientController extends Controller
{
    public function index(Request $request, BroadcastCampaign $campaign)
    {
        $request->validate(['status' => ['nullable', Rule::enum(BroadcastRecipientStatus::class)]]);

        $recipients = $campaign->recipients()
            ->leftJoin('notifications', 'notifications.id', '=', 'broadcast_recipients.notification_id')
            ->select('broadcast_recipients.*', 'notifications.read_at as notification_read_at')
            ->when($request->filled('status'), fn ($query) => $query->where('broadcast_recipients.status', $request->string('status')->toString()))
            ->when($request->filled('q'), fn ($query) => $query->whereLike('broadcast_recipients.email', '%'.$request->string('q').'%'))
            ->orderBy('broadcast_recipients.email')
            ->orderBy('broadcast_recipients.id')
            ->paginate($this->perPage($request, 50, 200));

        return ApiResponse::paginated(BroadcastRecipientResource::collection($recipients));
    }
}
