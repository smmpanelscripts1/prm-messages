<?php

namespace Prm\Messages\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;
use InvalidArgumentException;
use Prm\Messages\PublicMessage;

class PublicMessageSerializer extends AbstractSerializer
{
    protected $type = 'pm-public-messages';

    protected function getDefaultAttributes($message)
    {
        if (! ($message instanceof PublicMessage)) {
            throw new InvalidArgumentException(
                get_class($this).' can only serialize instances of '.PublicMessage::class
            );
        }

        $actor = $this->getActor();

        return [
            'content' => $message->content,
            'createdAt' => $this->formatDate($message->created_at),
            'canDelete' => ! $actor->isGuest() && (
                (int) $actor->id === (int) $message->user_id || $actor->isAdmin()
            ),
        ];
    }

    protected function user($message)
    {
        return $this->hasOne($message, BasicUserSerializer::class);
    }
}
