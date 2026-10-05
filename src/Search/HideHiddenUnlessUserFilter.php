<?php

namespace Prm\Messages\Search;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchCriteria;
use Flarum\Search\SearchState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class HideHiddenUnlessUserFilter
{
    public function __invoke(SearchState $state, SearchCriteria $criteria): void
    {
        $userId = Arr::get($criteria->filters, 'user');

        if ($userId !== null && $userId !== '') {
            return;
        }

        $actor = $state->getActor();

        /** @var DatabaseSearchState $state */
        $state->getQuery()->whereHas('states', function (Builder $q) use ($actor) {
            $q->where('user_id', $actor->id)->whereNull('hidden_at');
        });
    }
}
