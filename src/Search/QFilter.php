<?php

namespace Prm\Messages\Search;

use Flarum\Search\AbstractFulltextFilter;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchState;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends AbstractFulltextFilter<DatabaseSearchState>
 */
class QFilter extends AbstractFulltextFilter
{
    public function search(SearchState $state, string $value): void
    {
        $search = trim($value);

        if ($search === '') {
            return;
        }

        $actor = $state->getActor();
        $like = '%'.addcslashes($search, '%_\\').'%';

        $state->getQuery()->where(function (Builder $q) use ($actor, $like) {
            $q->where('subject', 'like', $like)
                ->orWhere('last_message_preview', 'like', $like)
                ->orWhereHas('userLow', function (Builder $user) use ($actor, $like) {
                    $user->where('id', '!=', $actor->id)->where('username', 'like', $like);
                })
                ->orWhereHas('userHigh', function (Builder $user) use ($actor, $like) {
                    $user->where('id', '!=', $actor->id)->where('username', 'like', $like);
                });
        });
    }
}
