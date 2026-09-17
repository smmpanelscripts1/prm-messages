<?php

namespace Prm\Messages;

use Carbon\Carbon;
use Flarum\User\User;

class PmGuard
{
    public static function isSuspended(User $user): bool
    {
        $until = $user->suspended_until ?? null;

        if (! $until) {
            return false;
        }

        if ($until instanceof Carbon) {
            return $until->isFuture();
        }

        try {
            return Carbon::parse($until)->isFuture();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function assertCanMessage(User $actor, User $recipient): void
    {
        $actor->assertCan('create', Conversation::class);

        if ((int) $actor->id === (int) $recipient->id) {
            throw new \Flarum\Foundation\ValidationException([
                'recipientId' => 'You cannot message yourself.',
            ]);
        }

        if (self::isSuspended($recipient) && ! $actor->isAdmin()) {
            throw new \Flarum\Foundation\ValidationException([
                'recipientId' => 'This user cannot receive messages.',
            ]);
        }
    }
}
