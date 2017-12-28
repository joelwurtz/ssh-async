<?php

declare(strict_types=1);

namespace Amp\SSH\Message;

class ChannelWindowAdjust implements Message
{
    public function encode(): string
    {
        return pack(
            'C',
            self::getNumber()
        );
    }

    public static function decode(string $payload)
    {
    }

    public static function getNumber(): int
    {
        return self::SSH_MSG_CHANNEL_WINDOW_ADJUST;
    }
}