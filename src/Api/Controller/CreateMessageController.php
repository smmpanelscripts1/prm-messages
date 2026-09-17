<?php

namespace Prm\Messages\Api\Controller;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Notification\NotificationSyncer;
use Illuminate\Support\Arr;
use Prm\Messages\Api\Serializer\MessageSerializer;
use Prm\Messages\Conversation;
use Prm\Messages\Message;
use Prm\Messages\Notification\MessageReceivedBlueprint;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class CreateMessageController extends AbstractCreateController
{
    public $serializer = MessageSerializer::class;

    public $include = ['user', 'conversation', 'conversation.userLow', 'conversation.userHigh', 'conversation.lastUser'];

    /**
     * @var NotificationSyncer
     */
    protected $notifications;

    public function __construct(NotificationSyncer $notifications)
    {
        $this->notifications = $notifications;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $attributes = Arr::get($request->getParsedBody(), 'data.attributes', []);
        $relationships = Arr::get($request->getParsedBody(), 'data.relationships', []);

        $conversationId = Arr::get($relationships, 'conversation.data.id');
        $conversation = Conversation::query()->findOrFail($conversationId);

        $actor->assertCan('reply', $conversation);

        $content = trim((string) Arr::get($attributes, 'content', ''));
        if ($content === '' || mb_strlen($content) > Message::MAX_LENGTH) {
            throw new ValidationException(['content' => 'A message is required.']);
        }

        $conversation->load(['userLow', 'userHigh', 'states']);
        $message = $conversation->postMessage($actor, $content);
        $message->setRelation('user', $actor);
        $message->setRelation('conversation', $conversation);

        $recipient = $conversation->otherParty($actor);
        $this->notifications->sync(new MessageReceivedBlueprint($message), [$recipient]);

        return $message;
    }
}
