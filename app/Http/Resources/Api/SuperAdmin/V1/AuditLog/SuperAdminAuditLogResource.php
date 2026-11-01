<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\AuditLog;

use App\Models\SuperAdmin\SuperAdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SuperAdminAuditLog
 */
class SuperAdminAuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'hash' => $this->hash,
            'action' => $this->action,
            'actor' => $this->whenLoaded('admin', fn () => $this->admin === null ? null : [
                'id' => $this->admin->id,
                'name' => $this->admin->name,
                'email' => $this->admin->email,
            ]),
            'actor_label' => $this->actor_label,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'before' => $this->before,
            'after' => $this->after,
            'reason' => $this->reason,
            'ip_address' => $this->ip_address,
            'request_id' => $this->request_id,
            'created_at' => $this->created_at,
        ];
    }
}
