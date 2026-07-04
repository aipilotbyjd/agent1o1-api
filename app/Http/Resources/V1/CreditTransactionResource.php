<?php

namespace App\Http\Resources\V1;

use App\Models\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditTransaction
 */
class CreditTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'credits' => $this->credits,
            'description' => $this->description,
            'created_at' => $this->created_at,
        ];
    }
}
