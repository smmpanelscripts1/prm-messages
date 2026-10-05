<?php

namespace Prm\Messages;

use Flarum\Api\Resource as CoreResource;
use Flarum\Extend;
use Flarum\Search\Database\DatabaseSearchDriver;
use Prm\Messages\Access\ConversationPolicy;
use Prm\Messages\Api\ForumResourceFields;
use Prm\Messages\Api\Resource\ConversationResource;
use Prm\Messages\Api\Resource\MessageResource;
use Prm\Messages\Api\Resource\PublicMessageResource;
use Prm\Messages\Api\UserResourceFields;
use Prm\Messages\Notification\MessageReceivedBlueprint;
use Prm\Messages\Search\ConversationSearcher;
use Prm\Messages\Search\HideHiddenUnlessUserFilter;
use Prm\Messages\Search\QFilter;
use Prm\Messages\Search\UserFilter;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->route('/messages', 'messages')
        ->route('/messages/{id}', 'messages.show')
        ->route('/chat', 'publicChat'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Settings())
        ->default('prm-messages.public_chat', '0')
        ->serializeToForum('publicChatEnabled', 'prm-messages.public_chat', function ($value) {
            return $value === '1' || $value === 1 || $value === true;
        }),

    new Extend\ApiResource(ConversationResource::class),
    new Extend\ApiResource(MessageResource::class),
    new Extend\ApiResource(PublicMessageResource::class),

    (new Extend\ApiResource(CoreResource\ForumResource::class))
        ->fields(ForumResourceFields::class),

    (new Extend\ApiResource(CoreResource\UserResource::class))
        ->fields(UserResourceFields::class),

    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addSearcher(Conversation::class, ConversationSearcher::class)
        ->addFilter(ConversationSearcher::class, UserFilter::class)
        ->setFulltext(ConversationSearcher::class, QFilter::class)
        ->addMutator(ConversationSearcher::class, HideHiddenUnlessUserFilter::class),

    (new Extend\Policy())
        ->modelPolicy(Conversation::class, ConversationPolicy::class),

    (new Extend\Notification())
        ->type(MessageReceivedBlueprint::class, ['alert']),
];
