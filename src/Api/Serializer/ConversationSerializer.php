<?php

namespace Prm\Messages\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;
use InvalidArgumentException;
use Prm\Messages\Conversation;

class ConversationSerializer extends AbstractSerializer
{
    protected $type = 'pm-conversations';

    protected function getDefaultAttributes($conversation)
    {
        if (! ($conversation instanceof Conversation)) {
            throw new InvalidArgumentException(
                get_class($this).' can only serialize instances of '.Conversation::class
            );
        }

        $actor = $this->getActor();
        $state = $conversation->stateFor($actor);

        return [
            'subject' => $conversation->subject,
            'preview' => $conversation->last_message_preview,
            'unreadCount' => $state ? (int) $state->unread_count : 0,
            'hidden' => $state && $state->hidden_at !== null,
            'createdAt' => $this->formatDate($conversation->created_at),
            'lastMessageAt' => $this->formatDate($conversation->last_message_at),
            'canReply' => $actor->can('reply', $conversation),
            'canHide' => $actor->can('hide', $conversation),
        ];
    }

    protected function userLow($conversation)
    {
        return $this->hasOne($conversation, BasicUserSerializer::class);
    }

    protected function userHigh($conversation)
    {
        return $this->hasOne($conversation, BasicUserSerializer::class);
    }

    protected function lastUser($conversation)
    {
        return $this->hasOne($conversation, BasicUserSerializer::class);
    }

    protected function messages($conversation)
    {
        return $this->hasMany($conversation, MessageSerializer::class);
    }
}
