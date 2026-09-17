<?php

namespace Prm\Messages\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;
use InvalidArgumentException;
use Prm\Messages\Message;

class MessageSerializer extends AbstractSerializer
{
    protected $type = 'pm-messages';

    protected function getDefaultAttributes($message)
    {
        if (! ($message instanceof Message)) {
            throw new InvalidArgumentException(
                get_class($this).' can only serialize instances of '.Message::class
            );
        }

        return [
            'content' => $message->content,
            'createdAt' => $this->formatDate($message->created_at),
        ];
    }

    protected function user($message)
    {
        return $this->hasOne($message, BasicUserSerializer::class);
    }

    protected function conversation($message)
    {
        return $this->hasOne($message, ConversationSerializer::class);
    }
}
