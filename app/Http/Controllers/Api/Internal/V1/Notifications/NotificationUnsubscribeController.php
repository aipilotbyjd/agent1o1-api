<?php

namespace App\Http\Controllers\Api\Internal\V1\Notifications;

use App\Enums\Notifications\NotificationType;
use App\Enums\Notifications\NotificationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Notifications\UpdateNotificationPreferencesRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Notifications\PreferenceWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RFC 8058 one-click unsubscribe from one event's email, POSTed by the mail
 * client from the `List-Unsubscribe` header. There is no session, so the
 * URL's signature is the authentication; it names the member, the event and
 * (for workspace events) the workspace.
 */
class NotificationUnsubscribeController extends Controller
{
    public function __invoke(Request $request, User $user, PreferenceWriter $writer): JsonResponse
    {
        $event = NotificationType::tryFrom((string) $request->query('event'));
        abort_if($event === null, 404);

        $scope = $event->definition()->scope;
        abort_unless(UpdateNotificationPreferencesRequest::acceptsKey($event->value, $scope), 404);

        $change = [['key' => $event->value, 'email' => false]];

        if ($scope === NotificationScope::Workspace) {
            $workspace = Workspace::query()->findOrFail((string) $request->query('workspace'));
            abort_unless($workspace->members()->where('user_id', $user->id)->exists(), 404);
            $writer->applyToWorkspace($user, $workspace, $change);
        } else {
            $writer->applyToAccount($user, $change);
        }

        return ApiResponse::success(null, "You won't get \"{$event->label()}\" emails any more.");
    }
}
