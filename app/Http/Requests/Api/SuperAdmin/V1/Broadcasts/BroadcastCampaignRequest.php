<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Broadcasts;

use App\Enums\Broadcasts\BroadcastCategory;
use App\Models\Broadcasts\BroadcastCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An email campaign create (POST) or partial update (PATCH, drafts only; the
 * service refuses the rest). The subject can't contain a line break, which
 * would let it inject mail headers.
 */
class BroadcastCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $presence = $this->route('campaign') instanceof BroadcastCampaign ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:120'],
            'category' => ['sometimes', Rule::enum(BroadcastCategory::class)],
            'subject' => [$presence, 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'body_markdown' => [$presence, 'string', 'max:20000'],
            ...AudienceRules::for(),
        ];
    }
}
