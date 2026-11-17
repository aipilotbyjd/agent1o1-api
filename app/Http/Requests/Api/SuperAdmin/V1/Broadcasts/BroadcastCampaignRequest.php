<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Broadcasts;

use App\Enums\Broadcasts\BroadcastCategory;
use App\Enums\Broadcasts\BroadcastChannel;
use App\Models\Broadcasts\BroadcastCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A campaign create (POST) or partial update (PATCH, drafts only; the service
 * refuses the rest). The subject can't contain a line break, which would let
 * it inject mail headers. What is required follows the channel the campaign
 * will have once saved: the email body when it goes by email, the summary
 * when it goes to the notification bell.
 *
 * The notification's button may point inside the app (a path, which may hold
 * `:workspace` for the member's current workspace) or to an https page; any
 * other scheme (`javascript:`, `data:`, plain http) is refused.
 */
class BroadcastCampaignRequest extends FormRequest
{
    /** A path inside the app (not `//host`), or an https address. */
    public const ACTION_URL_PATTERN = '#^(/(?![/\\\\])\S*|https://[^\s/?#]+\S*)$#i';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $existing = $this->route('campaign');
        $editing = $existing instanceof BroadcastCampaign;
        $presence = $editing ? 'sometimes' : 'required';
        $channel = BroadcastChannel::tryFrom((string) $this->input('channel', '')) ?? ($editing ? $existing->channel : BroadcastChannel::Email);

        $needsBody = $channel->includesEmail() && (! $editing || $this->has('body_markdown') || blank($existing->body_markdown));
        $needsSummary = $channel->includesInApp() && (! $editing || $this->has('notification_summary') || blank($existing->notification_summary));

        return [
            'name' => [$presence, 'string', 'max:120'],
            'category' => ['sometimes', Rule::enum(BroadcastCategory::class)],
            'channel' => ['sometimes', Rule::enum(BroadcastChannel::class)],
            'subject' => [$presence, 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'body_markdown' => [$needsBody ? 'required' : 'nullable', 'string', 'max:20000'],
            'notification_summary' => [$needsSummary ? 'required' : 'nullable', 'string', 'max:500'],
            'action_label' => ['nullable', 'required_with:action_url', 'string', 'max:40', 'not_regex:/[\r\n]/'],
            'action_url' => ['nullable', 'required_with:action_label', 'string', 'max:2048', 'regex:'.self::ACTION_URL_PATTERN],
            ...AudienceRules::for(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body_markdown.required' => 'Write the email: this campaign goes out by email.',
            'notification_summary.required' => 'Write the notification summary: this campaign goes to the notification bell.',
            'action_url.regex' => 'The button link must be a path in the app (starting with /) or an https:// address.',
        ];
    }
}
