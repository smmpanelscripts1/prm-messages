<?php

namespace Prm\Messages\Api\Controller;

use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Support\Arr;
use Prm\Messages\PublicChat;
use Prm\Messages\PublicMessage;
use Psr\Http\Message\ServerRequestInterface;

class DeletePublicMessageController extends AbstractDeleteController
{
    protected function delete(ServerRequestInterface $request)
    {
        $actor = RequestUtil::getActor($request);
        PublicChat::assertEnabled();
        $actor->assertRegistered();

        $id = Arr::get($request->getQueryParams(), 'id');
        $message = PublicMessage::query()->findOrFail($id);

        if ((int) $actor->id !== (int) $message->user_id && ! $actor->isAdmin()) {
            throw new PermissionDeniedException();
        }

        $message->delete();
    }
}
