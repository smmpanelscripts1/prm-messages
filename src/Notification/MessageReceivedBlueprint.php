<?php

namespace Prm\Messages\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Prm\Messages\Conversation;
use Prm\Messages\Message;

class MessageReceivedBlueprint implements BlueprintInterface
{
    /**
     * @var Message
     */
    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function getFromUser()
    {
        return $this->message->user;
    }

    public function getSubject()
    {
        return $this->message->conversation;
    }

    public function getData()
    {
        return [
            'conversationId' => $this->message->conversation_id,
            'messageId' => $this->message->id,
            'preview' => $this->message->conversation
                ? $this->message->conversation->last_message_preview
                : mb_substr($this->message->content, 0, 140),
        ];
    }

    public static function getType()
    {
        return 'pmMessageReceived';
    }

    public static function getSubjectModel()
    {
        return Conversation::class;
    }
}
