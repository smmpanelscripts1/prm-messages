<?php

namespace Prm\Messages\Api\Controller;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Flarum\Notification\NotificationSyncer;
use Illuminate\Support\Arr;
use Prm\Messages\Api\Serializer\ConversationSerializer;
use Prm\Messages\Conversation;
use Prm\Messages\Message;
use Prm\Messages\Notification\MessageReceivedBlueprint;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ShowConversationController extends AbstractShowController
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
        $id = Arr::get($request->getQueryParams(), 'id');
        $conversation = Conversation::query()->findOrFail($id);

        $actor->assertCan('view', $conversation);

        $conversation->load(['userLow', 'userHigh', 'lastUser', 'states']);
        $state = $conversation->stateFor($actor);
        $cleared = $state ? (int) $state->unread_count : 0;
        $conversation->markRead($actor);
        $document->setMeta(['clearedUnread' => $cleared]);

        $recentIds = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(100)
            ->pluck('id');

        $messages = Message::query()
            ->with('user')
            ->whereIn('id', $recentIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $conversation->setRelation('messages', $messages);

        $lastFromOther = $messages->reverse()->first(function (Message $message) use ($actor) {
            return (int) $message->user_id !== (int) $actor->id;
        });

        if ($lastFromOther) {
            $lastFromOther->setRelation('conversation', $conversation);
            $this->notifications->sync(new MessageReceivedBlueprint($lastFromOther), []);
        }

        return $conversation;
    }
}
