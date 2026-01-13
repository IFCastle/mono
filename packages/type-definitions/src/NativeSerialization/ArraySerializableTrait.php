<?php

declare(strict_types=1);

namespace IfCastle\TypeDefinitions\NativeSerialization;

use IfCastle\Exceptions\RecursionLimitExceeded;
use IfCastle\Exceptions\SerializeException;
use IfCastle\Exceptions\UnSerializeException;

trait ArraySerializableTrait
{
    /**
     * @param <string|int|null, mixed>                 $array
     *
     * @return array<mixed>
     * @throws RecursionLimitExceeded
     * @throws SerializeException
     */
    protected function serializeToArray(array $array, ?ArraySerializableValidatorInterface $validator = null, int $recursion = 0, int $limit = 32): array
    {
        $result = [];

        if ($limit > 32) {
            throw new RecursionLimitExceeded($limit);
        }

        foreach ($array as $key => $value) {
            if ($value instanceof ArraySerializableInterface) {
                $result[$key] = $value->toArray($validator);
            } elseif (\is_scalar($value) || \is_null($value)) {
                $result[$key] = $value;
            } elseif (\is_array($value)) {
                $result[$key] = self::serializeToArray($value, $validator, $recursion++, $limit);
            } else {
                throw new SerializeException('The value of the context should be scalar', $value, 'array', $this);
            }
        }

        return $result;
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    protected static function unserializeFromArray(array $array): array
    {
        $context                    = [];
        $stack                      = [];
        $iterator                   = new \ArrayIterator($array);
        $iterator->rewind();

        while (true) {

            while ($iterator->valid()) {
                $value              = $iterator->current();
                $key                = $iterator->key();

                if (\is_array($value)) {

                    $iterator->next();
                    $context[$key]  = [];
                    $stack[]        = [$iterator, &$context];

                    $context        = &$context[$key];
                    $iterator       = new \ArrayIterator($value);
                    $iterator->rewind();
                } elseif (\is_scalar($value) || \is_null($value)) {
                    $context[$key]  = $value;
                    $iterator->next();
                } else {
                    throw new UnSerializeException('The value of the context should be scalar', 'array', $value);
                }
            }

            if ($stack === []) {
                break;
            }

            $popped                  = \array_pop($stack);
            $iterator                = $popped[0];
            $context                 = &$popped[1];
        }

        return $context;
    }
}
