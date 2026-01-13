<?php

declare(strict_types=1);

namespace IfCastle\Swoole;

use Swoole\Coroutine\Channel;

class CancellationByTrigger extends CancellationAbstract
{
    private readonly Channel $channel;

    public function __construct(callable $setter, string $message = 'The operation was cancelled')
    {
        parent::__construct($message);

        $this->channel              = new Channel(1);
        $channel                    = $this->channel;
        $setter(static fn(?\Throwable $throwable = null) => $channel->push($throwable));
    }

    #[\Override]
    protected function await(): void
    {
        $throwable                  = $this->channel->pop();

        if ($throwable !== null) {
            throw $throwable;
        }
    }
}
