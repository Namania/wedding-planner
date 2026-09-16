<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class GalleryPhotoChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public string $action,
        public array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('gallery')];
    }

    public function broadcastAs(): string
    {
        return 'photo.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'payload' => $this->payload,
        ];
    }
}
