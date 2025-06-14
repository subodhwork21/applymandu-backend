<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $chatId;
    public array $messageIds;
    public int $readerId;

    /**
     * Create a new event instance.
     */
    public function __construct(int $chatId, array $messageIds, int $readerId)
    {
        $this->chatId = $chatId;
        $this->messageIds = $messageIds;
        $this->readerId = $readerId;
    }



    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->chatId),
        ];
    }

    public function broadcastWith()
    {
        return [
            'chat_id' => $this->chatId,
            'message_ids' => $this->messageIds,
            'user_id' => $this->readerId
        ];
    }
}
