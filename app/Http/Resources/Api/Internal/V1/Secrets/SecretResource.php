<?php

namespace App\Http\Resources\Api\Internal\V1\Secrets;

use App\Models\Secrets\Secret;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `value` is only ever included for a non-secret entry (a plain variable).
 * A secret's value is write-only: there is no endpoint, and no combination
 * of permissions, that reads it back.
 *
 * @mixin Secret
 */
class SecretResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'description' => $this->description,
            'is_secret' => $this->is_secret,
            'value' => $this->when(! $this->is_secret, fn () => $this->value),
            'reference' => "{{ secrets.{$this->key} }}",
            'last_used_at' => $this->last_used_at,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
