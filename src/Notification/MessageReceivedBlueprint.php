<?php

namespace Prm\Messages\Notification;

use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;
use Prm\Messages\Conversation;
use Prm\Messages\Message;

class MessageReceivedBlueprint implements BlueprintInterface, AlertableInterface
{
    public function __construct(
        public Message $message
    ) {
    }

    public function getFromUser(): ?User
    {
        return $this->message->user;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->message->conversation;
    }

    public function getData(): mixed
    {
        return [
            'conversationId' => $this->message->conversation_id,
            'messageId' => $this->message->id,
            'preview' => $this->message->conversation
                ? $this->message->conversation->last_message_preview
                : mb_substr($this->message->content, 0, 140),
        ];
    }

    public static function getType(): string
    {
        return 'pmMessageReceived';
    }

    public static function getSubjectModel(): string
    {
        return Conversation::class;
    }
}
