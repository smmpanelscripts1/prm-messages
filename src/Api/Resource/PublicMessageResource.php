<?php

namespace Prm\Messages\Api\Resource;

use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\Eloquent\Builder;
use Prm\Messages\PmGuard;
use Prm\Messages\PublicChat;
use Prm\Messages\PublicMessage;
use Tobyz\JsonApiServer\Context;

/**
 * @extends AbstractDatabaseResource<PublicMessage>
 */
class PublicMessageResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'pm-public-messages';
    }

    public function model(): string
    {
        return PublicMessage::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        // Public chat is gated on endpoints; listing uses a custom query.
    }

    public function query(Context $context): object
    {
        if ($context->listing(self::class)) {
            PublicChat::assertEnabled();

            $ids = PublicMessage::query()
                ->orderByDesc('id')
                ->limit(80)
                ->pluck('id');

            return PublicMessage::query()
                ->whereIn('id', $ids)
                ->orderBy('id');
        }

        return parent::query($context);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->defaultInclude(['user'])
                ->paginate(80, 80),
            Endpoint\Create::make()
                ->authenticated()
                ->visible(function (FlarumContext $context) {
                    return PublicChat::enabled()
                        && ! $context->getActor()->isGuest()
                        && ! PmGuard::isSuspended($context->getActor());
                })
                ->defaultInclude(['user']),
            Endpoint\Delete::make()
                ->authenticated()
                ->visible(function (PublicMessage $message, FlarumContext $context) {
                    if (! PublicChat::enabled()) {
                        return false;
                    }

                    $actor = $context->getActor();

                    return (int) $actor->id === (int) $message->user_id || $actor->isAdmin();
                }),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('content')
                ->requiredOnCreate()
                ->writableOnCreate()
                ->minLength(1)
                ->maxLength(PublicMessage::MAX_LENGTH),
            Schema\DateTime::make('createdAt'),
            Schema\Boolean::make('canDelete')
                ->get(function (PublicMessage $message, FlarumContext $context) {
                    $actor = $context->getActor();

                    return ! $actor->isGuest() && (
                        (int) $actor->id === (int) $message->user_id || $actor->isAdmin()
                    );
                }),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt'),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        PublicChat::assertEnabled();

        $actor = $context->getActor();
        $actor->assertRegistered();

        if (PmGuard::isSuspended($actor)) {
            throw new ValidationException(['content' => 'You cannot post in chat.']);
        }

        $content = trim((string) $model->content);
        if ($content === '' || mb_strlen($content) > PublicMessage::MAX_LENGTH) {
            throw new ValidationException(['content' => 'A message is required.']);
        }

        $model->user_id = $actor->id;
        $model->content = $content;

        return $model;
    }

    public function created(object $model, Context $context): ?object
    {
        $model->setRelation('user', $context->getActor());

        return $model;
    }

    public function deleting(object $model, Context $context): void
    {
        PublicChat::assertEnabled();
        $context->getActor()->assertRegistered();

        $actor = $context->getActor();

        if ((int) $actor->id !== (int) $model->user_id && ! $actor->isAdmin()) {
            throw new PermissionDeniedException();
        }
    }
}
