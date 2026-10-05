<?php

namespace Prm\Messages\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Prm\Messages\Conversation;
use Prm\Messages\ConversationState;
use Prm\Messages\PmGuard;
use Prm\Messages\PublicChat;

class ForumResourceFields
{
    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('canStartPm')
                ->get(function (object $model, Context $context) {
                    $actor = $context->getActor();

                    return ! $actor->isGuest() && $actor->can('create', Conversation::class);
                }),
            Schema\Integer::make('unreadPmCount')
                ->get(function (object $model, Context $context) {
                    $actor = $context->getActor();

                    if ($actor->isGuest() || ! $actor->can('create', Conversation::class)) {
                        return 0;
                    }

                    return (int) ConversationState::query()
                        ->where('user_id', $actor->id)
                        ->whereNull('hidden_at')
                        ->sum('unread_count');
                }),
            Schema\Boolean::make('canPostPublicChat')
                ->get(function (object $model, Context $context) {
                    $actor = $context->getActor();

                    return PublicChat::enabled() && ! $actor->isGuest() && ! PmGuard::isSuspended($actor);
                }),
        ];
    }
}
