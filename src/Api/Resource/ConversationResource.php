<?php

namespace Prm\Messages\Api\Resource;

use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Prm\Messages\Conversation;
use Prm\Messages\Message;
use Prm\Messages\Notification\MessageReceivedBlueprint;
use Prm\Messages\PmGuard;
use Tobyz\JsonApiServer\Context;

/**
 * @extends AbstractDatabaseResource<Conversation>
 */
class ConversationResource extends AbstractDatabaseResource
{
    public function __construct(
        protected NotificationSyncer $notifications
    ) {
    }

    public function type(): string
    {
        return 'pm-conversations';
    }

    public function model(): string
    {
        return Conversation::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $actor = $context->getActor();

        $query->where(function (Builder $q) use ($actor) {
            $q->where('user_low_id', $actor->id)
                ->orWhere('user_high_id', $actor->id);
        });
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->visible(fn (FlarumContext $context) => $context->getActor()->can('create', Conversation::class))
                ->defaultInclude(['userLow', 'userHigh', 'lastUser'])
                ->defaultSort('-lastMessageAt')
                ->paginate(20, 50),
            Endpoint\Show::make()
                ->authenticated()
                ->can('view')
                ->defaultInclude(['userLow', 'userHigh', 'lastUser', 'messages', 'messages.user'])
                ->after([$this, 'afterShow'])
                ->meta([
                    Schema\Integer::make('clearedUnread')
                        ->get(fn ($model, FlarumContext $context) => (int) $context->getParam('clearedUnread', 0)),
                ]),
            Endpoint\Create::make()
                ->authenticated()
                ->visible(fn (FlarumContext $context) => $context->getActor()->can('create', Conversation::class))
                ->defaultInclude(['userLow', 'userHigh', 'lastUser', 'messages', 'messages.user']),
            Endpoint\Update::make()
                ->authenticated()
                ->can('hide')
                ->defaultInclude(['userLow', 'userHigh', 'lastUser']),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Integer::make('recipientId')
                ->writable(fn (Conversation $conversation, FlarumContext $context) => $context->creating())
                ->requiredOnCreate()
                ->hidden()
                ->set(function (Conversation $conversation, $value) {
                    $conversation->setAttribute('_recipient_id', (int) $value);
                }),
            Schema\Str::make('subject')
                ->writable(fn (Conversation $conversation, FlarumContext $context) => $context->creating())
                ->nullable()
                ->maxLength(160)
                ->get(fn (Conversation $conversation) => $conversation->subject)
                ->set(function (Conversation $conversation, ?string $value) {
                    $value = is_string($value) ? trim($value) : '';
                    $conversation->setAttribute('_subject', $value !== '' ? $value : null);
                }),
            Schema\Str::make('content')
                ->writable(fn (Conversation $conversation, FlarumContext $context) => $context->creating())
                ->requiredOnCreate()
                ->hidden()
                ->minLength(1)
                ->maxLength(Message::MAX_LENGTH)
                ->set(function (Conversation $conversation, string $value) {
                    $conversation->setAttribute('_content', trim($value));
                }),
            Schema\Str::make('preview')
                ->get(fn (Conversation $conversation) => $conversation->last_message_preview),
            Schema\Integer::make('unreadCount')
                ->get(function (Conversation $conversation, FlarumContext $context) {
                    $state = $conversation->stateFor($context->getActor());

                    return $state ? (int) $state->unread_count : 0;
                }),
            Schema\Boolean::make('hidden')
                ->writable(fn (Conversation $conversation, FlarumContext $context) => $context->updating())
                ->get(function (Conversation $conversation, FlarumContext $context) {
                    $state = $conversation->stateFor($context->getActor());

                    return $state && $state->hidden_at !== null;
                })
                ->set(function (Conversation $conversation, bool $value, FlarumContext $context) {
                    if ($value) {
                        $conversation->hideFor($context->getActor());
                    }
                }),
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('lastMessageAt'),
            Schema\Boolean::make('canReply')
                ->get(fn (Conversation $conversation, FlarumContext $context) => $context->getActor()->can('reply', $conversation)),
            Schema\Boolean::make('canHide')
                ->get(fn (Conversation $conversation, FlarumContext $context) => $context->getActor()->can('hide', $conversation)),

            Schema\Relationship\ToOne::make('userLow')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('userHigh')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('lastUser')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToMany::make('messages')
                ->type('pm-messages')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('lastMessageAt'),
            SortColumn::make('createdAt'),
        ];
    }

    public function create(object $model, Context $context): object
    {
        $actor = $context->getActor();
        $recipientId = (int) $model->getAttribute('_recipient_id');
        $content = trim((string) $model->getAttribute('_content'));
        $subject = $model->getAttribute('_subject');

        if ($content === '' || mb_strlen($content) > Message::MAX_LENGTH) {
            throw new ValidationException(['content' => 'A message is required.']);
        }

        if (is_string($subject) && mb_strlen($subject) > 160) {
            throw new ValidationException(['subject' => 'Subject is too long.']);
        }

        $recipient = User::query()->findOrFail($recipientId);
        PmGuard::assertCanMessage($actor, $recipient);

        $conversation = Conversation::startNew($actor, $recipient, is_string($subject) ? $subject : null);
        $message = $conversation->postMessage($actor, $content);
        $message->setRelation('user', $actor);
        $message->setRelation('conversation', $conversation);

        $conversation->load(['userLow', 'userHigh', 'lastUser', 'states']);
        $conversation->setRelation(
            'messages',
            $conversation->messages()->with('user')->orderBy('created_at')->get()
        );

        $this->notifications->sync(new MessageReceivedBlueprint($message), [$recipient]);

        return $conversation;
    }

    public function update(object $model, Context $context): object
    {
        // Hide is applied via the hidden field setter; avoid re-saving conversation columns.
        $model->load(['userLow', 'userHigh', 'lastUser', 'states']);

        return $model;
    }

    public function afterShow(FlarumContext $context, Conversation $conversation): Conversation
    {
        $actor = $context->getActor();

        $conversation->load(['userLow', 'userHigh', 'lastUser', 'states']);
        $state = $conversation->stateFor($actor);
        $cleared = $state ? (int) $state->unread_count : 0;
        $context->setParam('clearedUnread', $cleared);
        $conversation->markRead($actor);

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
