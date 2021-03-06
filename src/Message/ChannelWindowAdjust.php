<?php

namespace Amp\SSH\Message;

use function Amp\SSH\Transport\read_byte;
use function Amp\SSH\Transport\read_uint32;

class ChannelWindowAdjust implements Message {
    public $recipientChannel;

    public $bytesToAdd;

    public function encode(): string {
        return \pack(
            'CN2',
            self::getNumber(),
            $this->recipientChannel,
            $this->bytesToAdd
        );
    }

    public static function decode(string $payload) {
        read_byte($payload);

        $message = new static;
        $message->recipientChannel = read_uint32($payload);
        $message->bytesToAdd = read_uint32($payload);

        return $message;
    }

    public static function getNumber(): int {
        return self::SSH_MSG_CHANNEL_WINDOW_ADJUST;
    }
}
