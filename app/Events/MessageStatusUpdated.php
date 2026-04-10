<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $status;
    public $conversationId;

    /**
     * Create a new event instance.
     */
    public function __construct($userId, $status, $conversationId = null)
    {
        $this->userId = $userId;
        $this->status = $status;
        $this->conversationId = $conversationId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Use a public channel for the conversation so guests can also see status updates
        return [
            new Channel("status.{$this->conversationId}"),
        ];
    }
}
