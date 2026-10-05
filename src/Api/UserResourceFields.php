<?php

namespace Prm\Messages\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\User\User;
use Prm\Messages\Conversation;
use Prm\Messages\PmGuard;

class UserResourceFields
{
    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('canReceivePm')
                ->get(function (User $user, Context $context) {
                    $actor = $context->getActor();

                    return ! $actor->isGuest()
                        && (int) $actor->id !== (int) $user->id
                        && $actor->can('create', Conversation::class)
                        && ! PmGuard::isSuspended($user);
                }),
        ];
    }
}
