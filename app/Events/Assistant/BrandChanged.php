<?php

namespace App\Events\Assistant;

use App\Services\Assistant\Branding\Brand;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells open frontends to refetch `/app-config` after a rename, so the new
 * name shows without a reload. Public channel: the brand is public data.
 */
class BrandChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public const string CHANNEL = 'app-config';

    public function __construct(public Brand $brand) {}

    public function broadcastOn(): Channel
    {
        return new Channel(self::CHANNEL);
    }

    public function broadcastAs(): string
    {
        return 'brand.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['brand' => $this->brand->toArray()];
    }
}
