<?php

declare(strict_types=1);

namespace IfCastle\Swoole;

use IfCastle\Async\CancellationInterface;

class CompositeCancellation extends CancellationAbstract
{
    private readonly array $cancellations;

    public function __construct(CancellationInterface ...$cancellations)
    {
        $this->cancellations        = $cancellations;
        parent::__construct();
    }

    #[\Override]
    protected function await(): void {}
}
