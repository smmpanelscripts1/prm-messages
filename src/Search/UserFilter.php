<?php

namespace Prm\Messages\Search;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;
use Prm\Messages\Conversation;

/**
 * @implements FilterInterface<DatabaseSearchState>
 */
class UserFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'user';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $otherId = $this->asInt($value);
        $actorId = (int) $state->getActor()->id;
        [$low, $high] = Conversation::idsFor($actorId, $otherId);

        $state->getQuery()
            ->where('user_low_id', $low)
            ->where('user_high_id', $high);
    }
}
