<?php

namespace Prm\Messages\Api\Controller;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Prm\Messages\Api\Serializer\ConversationSerializer;
use Prm\Messages\Conversation;
use Prm\Messages\Message;
use Prm\Messages\Notification\MessageReceivedBlueprint;
use Prm\Messages\PmGuard;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class CreateConversationController extends AbstractCreateController
{
    public $serializer = ConversationSerializer::class;

    public $include = ['userLow', 'userHigh', 'lastUser', 'messages', 'messages.user'];

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

        $recipientId = (int) Arr::get($attributes, 'recipientId', Arr::get($relationships, 'recipient.data.id'));
        $recipient = User::query()->findOrFail($recipientId);

        PmGuard::assertCanMessage($actor, $recipient);

        $content = trim((string) Arr::get($attributes, 'content', ''));
        if ($content === '' || mb_strlen($content) > Message::MAX_LENGTH) {
            throw new ValidationException(['content' => 'A message is required.']);
        }

        $subject = trim((string) Arr::get($attributes, 'subject', ''));
        if (mb_strlen($subject) > 160) {
            throw new ValidationException(['subject' => 'Subject is too long.']);
        }

        $conversation = Conversation::startNew($actor, $recipient, $subject !== '' ? $subject : null);
        $message = $conversation->postMessage($actor, $content);
        $message->setRelation('user', $actor);
        $message->setRelation('conversation', $conversation);

        $conversation->load(['userLow', 'userHigh', 'lastUser', 'states']);
        $conversation->setRelation('messages', $conversation->messages()->with('user')->orderBy('created_at')->get());

        $this->notifications->sync(new MessageReceivedBlueprint($message), [$recipient]);

        return $conversation;
    }
}
