<?php

namespace Prm\Messages\Access;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;
use Prm\Messages\Conversation;

class ConversationPolicy extends AbstractPolicy
{
    public function create(User $actor)
    {
        return $actor->hasPermission('pm.start');
    }

    public function view(User $actor, Conversation $conversation)
    {
        if ($conversation->isParticipant($actor) && $actor->hasPermission('pm.start')) {
            return $this->allow();
        }
    }

    public function reply(User $actor, Conversation $conversation)
    {
        return $this->view($actor, $conversation);
    }

    public function hide(User $actor, Conversation $conversation)
    {
        return $this->view($actor, $conversation);
    }
}
