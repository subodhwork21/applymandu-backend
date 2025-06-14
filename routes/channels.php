<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

// For user-specific notification channels
Broadcast::channel('user.{userId}.messages', function ($user, $userId) {
    Log::debug("Authorizing user {$user->id} for user channel {$userId}");
    $hasAccess = (string) $user->id === (string) $userId;
    return $hasAccess;
});

// For chat-specific channels
Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    return true; 
});
