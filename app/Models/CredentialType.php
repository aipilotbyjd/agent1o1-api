<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'name',
    'description',
    'auth_type',
    'color',
    'icon',
    'docs_url',
    'fields',
    'oauth',
    'is_active',
    'sort_order',
])]
class CredentialType extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'oauth' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isOAuth(): bool
    {
        return $this->auth_type === 'oauth2';
    }
}
