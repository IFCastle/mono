<?php

declare(strict_types=1);

namespace IfCastle\Events;

use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;

class ExternalEvent extends BaseEvent implements ExternalEventInterface
{
    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new self(
            $array[self::EVENT_NAME] ?? '',
            $array[self::EVENT_PRODUCER] ?? null,
            $array[self::EVENT_TOPICS] ?? [],
            $array[self::EVENT_TIMESTAMP] ?? 0
        );
    }

    /**
     * @param array<string> $eventTopics
     */
    public function __construct(
        string                  $eventName,
        protected ?string       $eventProducer = null,
        protected array         $eventTopics = [],
        int                     $eventTimestamp = 0
    ) {
        parent::__construct($eventName, $eventTimestamp);
    }

    #[\Override]
    public function getEventProducer(): ?string
    {
        return $this->eventProducer;
    }

    /**
     * @return  $this
     */
    #[\Override]
    public function setEventProducer(string $eventProducer): static
    {
        $this->eventProducer        = $eventProducer;
        return $this;
    }

    #[\Override]
    public function getEventTopics(): array
    {
        return $this->eventTopics;
    }

    #[\Override]
    /**
     * @param array<string> $eventTopics
     */
    public function setEventTopics(array $eventTopics): static
    {
        $this->eventTopics          = $eventTopics;
        return $this;
    }

    #[\Override]
    public function addEventTopic(string $eventTopic): static
    {
        $this->eventTopics[]        = $eventTopic;
        return $this;
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return parent::toArray($validator) + [
            self::EVENT_PRODUCER    => $this->eventProducer,
            self::EVENT_TOPICS      => $this->eventTopics,
        ];
    }
}
