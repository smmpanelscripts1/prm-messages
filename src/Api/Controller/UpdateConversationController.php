<?php

namespace Prm\Messages\Api\Controller;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Prm\Messages\Api\Serializer\ConversationSerializer;
use Prm\Messages\Conversation;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class UpdateConversationController extends AbstractShowController
{
    public $serializer = ConversationSerializer::class;

    public $include = ['userLow', 'userHigh', 'lastUser'];

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $id = Arr::get($request->getQueryParams(), 'id');
        $conversation = Conversation::query()->findOrFail($id);

        $actor->assertCan('hide', $conversation);

        $attributes = Arr::get($request->getParsedBody(), 'data.attributes', []);

        if (! empty($attributes['hidden'])) {
            $conversation->hideFor($actor);
        }

        $conversation->load(['userLow', 'userHigh', 'lastUser', 'states']);

        return $conversation;
    }
}
