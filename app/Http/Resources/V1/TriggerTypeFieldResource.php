<?php

namespace App\Http\Resources\V1;

use App\Models\TriggerTypeField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TriggerTypeField
 */
class TriggerTypeFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field_name' => $this->field_name,
            'field_label' => $this->field_label,
            'field_type' => $this->field_type,
            'is_required' => $this->is_required,
            'is_secret' => $this->is_secret,
            'placeholder' => $this->placeholder,
            'help_text' => $this->help_text,
            'validation_regex' => $this->validation_regex,
            'options' => $this->options,
            'sort_order' => $this->sort_order,
        ];
    }
}
