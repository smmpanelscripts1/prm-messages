<?php

namespace Prm\Messages\Api\Controller;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Prm\Messages\Api\Serializer\PublicMessageSerializer;
use Prm\Messages\PmGuard;
use Prm\Messages\PublicChat;
use Prm\Messages\PublicMessage;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class CreatePublicMessageController extends AbstractCreateController
{
    public $serializer = PublicMessageSerializer::class;

    public $include = ['user'];

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        PublicChat::assertEnabled();
        $actor->assertRegistered();

        if (PmGuard::isSuspended($actor)) {
            throw new ValidationException(['content' => 'You cannot post in chat.']);
        }

        $content = trim((string) Arr::get($request->getParsedBody(), 'data.attributes.content', ''));
        if ($content === '' || mb_strlen($content) > PublicMessage::MAX_LENGTH) {
            throw new ValidationException(['content' => 'A message is required.']);
        }

        $message = new PublicMessage();
        $message->user_id = $actor->id;
        $message->content = $content;
        $message->save();
        $message->setRelation('user', $actor);

        return $message;
    }
}
