<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'workspace_id',
    'user_id',
    'event_key',
    'in_app',
    'email',
    'channel_ids',
])]
class NotificationPreference extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'email' => 'boolean',
            'channel_ids' => 'array',
        ];
    }
}
