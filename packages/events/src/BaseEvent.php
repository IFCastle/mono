<?php

declare(strict_types=1);

namespace IfCastle\Events;

use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;

class BaseEvent implements EventInterface, ArraySerializableInterface
{
    public function __construct(protected string $eventName,
        protected int $eventTimestamp       = 0
    ) {}

    #[\Override]
    public function getEventName(): string
    {
        return $this->eventName;
    }

    #[\Override]
    public function getEventTimestamp(): int
    {
        return $this->eventTimestamp;
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return [
            self::EVENT_NAME        => $this->eventName,
            self::EVENT_TIMESTAMP   => $this->eventTimestamp,
        ];
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new self(
            $array[self::EVENT_NAME] ?? '',
            $array[self::EVENT_TIMESTAMP] ?? 0
        );
    }

    /**
     * @param array<string, mixed> $array
     */
    protected function constructFromArray(array $array): static
    {
        $this->eventName            = $array[self::EVENT_NAME] ?? '';
        $this->eventTimestamp       = $array[self::EVENT_TIMESTAMP] ?? 0;

        return $this;
    }
}
