<?php

namespace Amp\SSH\Channel;

use Amp\Emitter;
use Amp\Iterator;
use Amp\Promise;
use Amp\SSH\Message\ChannelClose;
use Amp\SSH\Message\ChannelData;
use Amp\SSH\Message\ChannelEof;
use Amp\SSH\Message\ChannelExtendedData;
use Amp\SSH\Message\ChannelFailure;
use Amp\SSH\Message\ChannelOpen;
use Amp\SSH\Message\ChannelOpenConfirmation;
use Amp\SSH\Message\ChannelOpenFailure;
use Amp\SSH\Message\ChannelRequest;
use Amp\SSH\Message\ChannelSuccess;
use Amp\SSH\Transport\BinaryPacketWriter;
use Amp\Success;
use function Amp\asyncCall;
use function Amp\call;

/**
 * @internal
 */
abstract class Channel {
    protected $channelId;

    /** @var BinaryPacketWriter */
    protected $writer;

    /** @var Iterator */
    protected $channelMessage;

    protected $dataEmitter;

    protected $dataExtendedEmitter;

    protected $requestEmitter;

    protected $requestResultEmitter;

    public function __construct(BinaryPacketWriter $writer, Iterator $channelMessage, int $channelId) {
        $this->channelId = $channelId;
        $this->writer = $writer;
        $this->channelMessage = $channelMessage;
        $this->dataEmitter = new Emitter();
        $this->dataExtendedEmitter = new Emitter();
        $this->requestEmitter = new Emitter();
        $this->requestResultEmitter = new Emitter();
    }

    public function getChannelId(): int {
        return $this->channelId;
    }

    /**
     * @return Emitter
     */
    public function getDataEmitter(): Emitter {
        return $this->dataEmitter;
    }

    /**
     * @return Emitter
     */
    public function getDataExtendedEmitter(): Emitter {
        return $this->dataExtendedEmitter;
    }

    /**
     * @return Emitter
     */
    public function getRequestEmitter(): Emitter {
        return $this->requestEmitter;
    }

    protected function dispatch(): void {
        asyncCall(function () {
            while (yield $this->channelMessage->advance()) {
                $message = $this->channelMessage->getCurrent();

                if ($message instanceof ChannelData) {
                    $this->dataEmitter->emit($message);
                }

                if ($message instanceof ChannelExtendedData) {
                    $this->dataExtendedEmitter->emit($message);
                }

                if ($message instanceof ChannelRequest) {
                    $this->requestEmitter->emit($message);
                }

                if ($message instanceof ChannelSuccess || $message instanceof ChannelFailure) {
                    $this->requestResultEmitter->emit($message);
                }
            }
        });
    }

    public function open(): Promise {
        return call(function () {
            $channelOpen = new ChannelOpen();
            $channelOpen->senderChannel = $this->channelId;
            $channelOpen->channelType = $this->getType();

            yield $this->writer->write($channelOpen);
            yield $this->channelMessage->advance();

            $openResult = $this->channelMessage->getCurrent();

            if ($openResult instanceof ChannelOpenConfirmation) {
                $this->dispatch();

                return true;
            }

            if ($openResult instanceof ChannelOpenFailure) {
                throw new \RuntimeException('Failed to open channel : ' . $openResult->description);
            }

            throw new \RuntimeException('Invalid message receive');
        });
    }

    public function data(string $data): Promise {
        $message = new ChannelData();
        $message->data = $data;
        $message->recipientChannel = $this->channelId;

        if (empty($data)) {
            return new Success;
        }

        return $this->writer->write($message);
    }

    public function eof(): Promise {
        $message = new ChannelEof();
        $message->recipientChannel = $this->channelId;

        return $this->writer->write($message);
    }

    public function close(): Promise {
        return call(function () {
            $message = new ChannelClose();
            $message->recipientChannel = $this->channelId;

            yield $this->writer->write($message);

            $this->requestResultEmitter->complete();
            $this->requestEmitter->complete();
            $this->dataEmitter->complete();
            $this->dataExtendedEmitter->complete();
        });
    }

    protected function doRequest(ChannelRequest $request): Promise {
        return call(function () use ($request) {
            yield $this->writer->write($request);
            yield $this->requestResultEmitter->iterate()->advance();

            $message = $this->requestResultEmitter->iterate()->getCurrent();

            return $message instanceof ChannelSuccess;
        });
    }

    abstract protected function getType(): string;
}
