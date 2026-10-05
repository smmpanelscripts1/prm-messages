<?php

namespace Prm\Messages;

use Flarum\Http\Exception\RouteNotFoundException;
use Flarum\Settings\SettingsRepositoryInterface;

class PublicChat
{
    const SETTING = 'prm-messages.public_chat';

    public static function enabled(): bool
    {
        $value = resolve(SettingsRepositoryInterface::class)->get(self::SETTING, '0');

        return $value === '1' || $value === 1 || $value === true;
    }

    public static function assertEnabled(): void
    {
        if (! self::enabled()) {
            throw new RouteNotFoundException();
        }
    }
}
