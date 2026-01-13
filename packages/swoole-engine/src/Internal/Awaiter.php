<?php

declare(strict_types=1);

namespace IfCastle\Swoole\Internal;

use IfCastle\Async\CancellationInterface;
use IfCastle\Async\FutureInterface;
use IfCastle\Exceptions\CompositeException;
use IfCastle\Exceptions\UnexpectedValue;
use IfCastle\Swoole\Future;
use Swoole\Coroutine\Channel;

final class Awaiter
{
    /**
     * Awaits the first N successfully completed futures.
     * If {@code $count} is 0, then all futures are awaited.
     * If {@code $shouldIgnoreError} is true, then the error is ignored.
     *
     * @template Tk of array-key
     * @template Tv
     *
     * @param positive-int                      $futuresAwaitCount
     * @param iterable<Tk, FutureInterface<Tv>> $futures
     * @param CancellationInterface|null        $cancellation      Optional cancellation.
     * @param bool                              $shouldIgnoreError Whether to ignore errors.
     *
     * @return array{array<Tk, \Throwable>, array<Tk, Tv>} The first array contains the errors, the second array
     *                         contains the results.
     * @throws UnexpectedValue
     */
    public static function await(int                    $futuresAwaitCount,
        iterable               $futures,
        ?CancellationInterface $cancellation = null,
        bool                   $shouldIgnoreError = false
    ): array {
        if ($futuresAwaitCount < 0) {
            throw new UnexpectedValue('$futuresAwaitCount', $futuresAwaitCount, 'Count should be greater than 0');
        }

        $futuresCount               = \iterator_count($futures);

        if ($futuresCount === 0) {
            return [];
        }

        if ($futuresAwaitCount > $futuresCount) {
            throw new UnexpectedValue('$count', $futuresAwaitCount, 'Count should be less than or equal to the number of futures');
        }

        if ($futuresAwaitCount === 0) {
            $futuresAwaitCount      = $futuresCount;
        }

        $channel                    = new Channel(1);
        $handler                    = null;
        // Success results
        $results                    = [];
        // Futures errors
        $errors                     = [];
        // Futures keys: spl_object_id => index
        $futuresKeys                = [];
        // Awaiting futures
        $awaiting                   = [];
        $error                      = null;

        // Initialize...
        foreach ($futures as $index => $future) {

            if (false === $future instanceof Future) {
                throw new UnexpectedValue('futures', $future, Future::class);
            }

            $objectId               = (string) \spl_object_id($future);
            $futuresKeys[$objectId] = $index;
            $awaiting[$objectId]    = true;
        }

        $complete                   = static function () use (&$handler, $futures, $cancellation) {

            if (!\is_object($handler)) {
                return;
            }

            foreach ($futures as $future) {
                if ($future instanceof Future) {
                    $future->state->unsubscribe($handler);
                }
            }

            $cancellation?->unsubscribe((string) \spl_object_id($handler));
        };

        $handler                    = static function (mixed $futureStateOrException = null) use ($channel, $complete,
            &$results, &$errors, $futuresKeys, &$awaiting, &$error, $futuresAwaitCount, $shouldIgnoreError) {

            if ($futureStateOrException instanceof \Throwable) {
                try {
                    $error          = $futureStateOrException;
                    $channel->push(true);
                } finally {
                    $complete();
                }

                return;
            }

            if (false === $futureStateOrException instanceof FutureState) {
                return;
            }

            //
            // When the Future failed
            //
            if ($futureStateOrException->getThrowable() !== null) {

                if ($shouldIgnoreError) {
                    $id             = (string) \spl_object_id($futureStateOrException);

                    if (\array_key_exists($id, $awaiting)) {
                        $errors[$futuresKeys[$id]] = $futureStateOrException->getThrowable();
                        unset($awaiting[$id]);
                    }

                    $error          = match (true) {
                        $awaiting === []
                                    => new CompositeException('No more objects to wait for, but success was not achieved', ...$errors),
                        \count($awaiting) < ($futuresAwaitCount - \count($results))
                                    => new CompositeException('Too many errors, waiting aborted', ...$errors),
                        default     => null
                    };

                    if ($error !== null) {
                        try {
                            $channel->push(true);
                        } finally {
                            $complete();
                        }
                    }

                    return;
                }

                try {
                    $error          = $futureStateOrException->getThrowable();
                    $channel->push(true);
                } finally {
                    $complete();
                }

                return;
            }

            $id                     = (string) \spl_object_id($futureStateOrException);

            if (\array_key_exists($id, $futuresKeys)) {
                $results[$futuresKeys[$id]] = $futureStateOrException->getResult();
                unset($awaiting[$id]);
            }

            if ($awaiting === [] || $futuresAwaitCount <= \count($results)) {
                try {
                    $channel->push(true);
                } finally {
                    $complete();
                }
            }
        };

        foreach ($futures as $future) {
            if ($future instanceof Future) {
                $future->state->subscribe($handler);
            }
        }

        $cancellation?->subscribe($handler);

        try {
            $channel->pop();
        } finally {
            $complete();

            if ($error instanceof \Throwable) {
                throw new $error();
            }
        }

        return [$errors, $results];
    }
}
