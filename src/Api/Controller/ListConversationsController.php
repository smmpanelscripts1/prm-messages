<?php

namespace Prm\Messages\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Prm\Messages\Api\Serializer\ConversationSerializer;
use Prm\Messages\Conversation;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListConversationsController extends AbstractListController
{
    public $serializer = ConversationSerializer::class;

    public $include = ['userLow', 'userHigh', 'lastUser'];

    public $limit = 20;

    public $maxLimit = 50;

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('create', Conversation::class);

        $filters = $this->extractFilter($request);
        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);
        $include = $this->extractInclude($request);
        $otherId = (int) Arr::get($filters, 'user');
        $search = trim((string) Arr::get($filters, 'q', ''));

        $query = Conversation::query()
            ->where(function ($q) use ($actor) {
                $q->where('user_low_id', $actor->id)
                    ->orWhere('user_high_id', $actor->id);
            })
            ->with(['states', 'userLow', 'userHigh', 'lastUser'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($otherId) {
            [$low, $high] = Conversation::idsFor((int) $actor->id, $otherId);
            $query->where('user_low_id', $low)->where('user_high_id', $high);
        } else {
            $query->whereHas('states', function ($q) use ($actor) {
                $q->where('user_id', $actor->id)->whereNull('hidden_at');
            });
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($actor, $like) {
                $q->where('subject', 'like', $like)
                    ->orWhere('last_message_preview', 'like', $like)
                    ->orWhereHas('userLow', function ($user) use ($actor, $like) {
                        $user->where('id', '!=', $actor->id)->where('username', 'like', $like);
                    })
                    ->orWhereHas('userHigh', function ($user) use ($actor, $like) {
                        $user->where('id', '!=', $actor->id)->where('username', 'like', $like);
                    });
            });
        }

        $total = (clone $query)->count();
        $results = $query->skip($offset)->take($limit)->get();

        $document->setMeta(['total' => $total]);

        $this->loadRelations($results, $include);

        return $results;
    }
}
