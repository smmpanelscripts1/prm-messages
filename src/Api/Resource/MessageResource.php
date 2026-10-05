<?php

namespace Prm\Messages\Api\Resource;

use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Foundation\ValidationException;
use Flarum\Notification\NotificationSyncer;
use Illuminate\Database\Eloquent\Builder;
use Prm\Messages\Conversation;
use Prm\Messages\Message;
use Prm\Messages\Notification\MessageReceivedBlueprint;
use Tobyz\JsonApiServer\Context;

/**
 * @extends AbstractDatabaseResource<Message>
 */
class MessageResource extends AbstractDatabaseResource
{
    public function __construct(
        protected NotificationSyncer $notifications
    ) {
    }

    public function type(): string
    {
        return 'pm-messages';
    }

    public function model(): string
    {
        return Message::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $actor = $context->getActor();

        $query->whereHas('conversation', function (Builder $q) use ($actor) {
            $q->where('user_low_id', $actor->id)
                ->orWhere('user_high_id', $actor->id);
        });
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Create::make()
                ->authenticated()
                ->defaultInclude(['user', 'conversation', 'conversation.userLow', 'conversation.userHigh', 'conversation.lastUser']),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('content')
                ->requiredOnCreate()
                ->writableOnCreate()
                ->minLength(1)
                ->maxLength(Message::MAX_LENGTH),
            Schema\DateTime::make('createdAt'),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('conversation')
                ->type('pm-conversations')
                ->includable()
                ->writable(fn (Message $message, FlarumContext $context) => $context->creating())
                ->requiredOnCreate(),
        ];
    }

    public function sorts(): array
    {
        return [];
    }

    public function create(object $model, Context $context): object
    {
        $actor = $context->getActor();
        $conversationId = (int) $model->conversation_id;
        $conversation = Conversation::query()->findOrFail($conversationId);

        $actor->assertCan('reply', $conversation);

        $content = trim((string) $model->content);
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
