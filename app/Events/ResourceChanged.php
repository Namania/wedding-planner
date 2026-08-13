<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

// Un seul event générique pour toutes les ressources plutôt qu'une classe par
// modèle : les deux mariés partagent un unique canal, et le front distingue
// les ressources par leur nom plutôt que par le nom de l'event.
class ResourceChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public string $resource,
        public string $action,
        public array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('wedding')];
    }

    public function broadcastAs(): string
    {
        return 'resource.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'resource' => $this->resource,
            'action' => $this->action,
            'payload' => $this->payload,
        ];
    }
}
