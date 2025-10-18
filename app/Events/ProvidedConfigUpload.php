<?php

namespace App\Events;

use App\Services\Shared\Configuration\Configuration;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProvidedConfigUpload
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $fileName;
    public Configuration $configuration;
    /**
     * Create a new event instance.
     */
    public function __construct(string $fileName, Configuration $configuration)
    {
        $this->fileName = $fileName;
        $this->configuration = $configuration;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }

}
