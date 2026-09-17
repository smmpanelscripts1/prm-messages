<?php

namespace Prm\Messages\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Prm\Messages\Api\Serializer\PublicMessageSerializer;
use Prm\Messages\PublicChat;
use Prm\Messages\PublicMessage;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListPublicMessagesController extends AbstractListController
{
    public $serializer = PublicMessageSerializer::class;

    public $include = ['user'];

    public $limit = 80;

    public $maxLimit = 80;

    protected function data(ServerRequestInterface $request, Document $document)
    {
        RequestUtil::getActor($request);
        PublicChat::assertEnabled();

        $ids = PublicMessage::query()
            ->orderByDesc('id')
            ->limit(80)
            ->pluck('id');

        $messages = PublicMessage::query()
            ->with('user')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        $this->loadRelations($messages, $this->extractInclude($request));

        return $messages;
    }
}
