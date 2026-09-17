<?php

namespace Prm\Messages;

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\Extend;
use Flarum\User\User;
use Prm\Messages\Access\ConversationPolicy;
use Prm\Messages\Api\Controller\CreateConversationController;
use Prm\Messages\Api\Controller\CreateMessageController;
use Prm\Messages\Api\Controller\CreatePublicMessageController;
use Prm\Messages\Api\Controller\DeletePublicMessageController;
use Prm\Messages\Api\Controller\ListConversationsController;
use Prm\Messages\Api\Controller\ListPublicMessagesController;
use Prm\Messages\Api\Controller\ShowConversationController;
use Prm\Messages\Api\Controller\UpdateConversationController;
use Prm\Messages\Api\Serializer\ConversationSerializer;
use Prm\Messages\Notification\MessageReceivedBlueprint;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->route('/messages', 'messages')
        ->route('/messages/{id}', 'messages.show')
        ->route('/chat', 'publicChat'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->get('/pm-conversations', 'pm-conversations.index', ListConversationsController::class)
        ->post('/pm-conversations', 'pm-conversations.create', CreateConversationController::class)
        ->get('/pm-conversations/{id}', 'pm-conversations.show', ShowConversationController::class)
        ->patch('/pm-conversations/{id}', 'pm-conversations.update', UpdateConversationController::class)
        ->post('/pm-messages', 'pm-messages.create', CreateMessageController::class)
        ->get('/pm-public-messages', 'pm-public-messages.index', ListPublicMessagesController::class)
        ->post('/pm-public-messages', 'pm-public-messages.create', CreatePublicMessageController::class)
        ->delete('/pm-public-messages/{id}', 'pm-public-messages.delete', DeletePublicMessageController::class),

    (new Extend\Settings())
        ->default('prm-messages.public_chat', '0')
        ->serializeToForum('publicChatEnabled', 'prm-messages.public_chat', function ($value) {
            return $value === '1' || $value === 1 || $value === true;
        }),

    (new Extend\ApiSerializer(ForumSerializer::class))
        ->attributes(function (ForumSerializer $serializer) {
            $actor = $serializer->getActor();
            $canStart = ! $actor->isGuest() && $actor->can('create', Conversation::class);

            $unread = 0;
            if ($canStart) {
                $unread = (int) ConversationState::query()
                    ->where('user_id', $actor->id)
                    ->whereNull('hidden_at')
                    ->sum('unread_count');
            }

            $publicChat = PublicChat::enabled();

            return [
                'canStartPm' => $canStart,
                'unreadPmCount' => $unread,
                'canPostPublicChat' => $publicChat && ! $actor->isGuest() && ! PmGuard::isSuspended($actor),
            ];
        }),

    (new Extend\ApiSerializer(UserSerializer::class))
        ->attributes(function (UserSerializer $serializer, User $user) {
            $actor = $serializer->getActor();

            return [
                'canReceivePm' => ! $actor->isGuest()
                    && (int) $actor->id !== (int) $user->id
                    && $actor->can('create', Conversation::class)
                    && ! PmGuard::isSuspended($user),
            ];
        }),

    (new Extend\Policy())
        ->modelPolicy(Conversation::class, ConversationPolicy::class),

    (new Extend\Notification())
        ->type(MessageReceivedBlueprint::class, ConversationSerializer::class, ['alert']),
];
