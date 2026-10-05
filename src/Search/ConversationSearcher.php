<?php

namespace Prm\Messages\Search;

use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Prm\Messages\Conversation;

class ConversationSearcher extends AbstractSearcher
{
    public function getQuery(User $actor): Builder
    {
        return Conversation::query()
            ->where(function (Builder $q) use ($actor) {
                $q->where('user_low_id', $actor->id)
                    ->orWhere('user_high_id', $actor->id);
            })
            ->with(['states', 'userLow', 'userHigh', 'lastUser']);
    }
}
