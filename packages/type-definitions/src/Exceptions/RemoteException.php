<?php

declare(strict_types=1);

namespace IfCastle\TypeDefinitions\Exceptions;

use IfCastle\Exceptions\BaseException;
use IfCastle\TypeDefinitions\DefinitionAwareInterface;
use IfCastle\TypeDefinitions\DefinitionInterface;
use IfCastle\TypeDefinitions\DefinitionMutableInterface;
use IfCastle\TypeDefinitions\DefinitionStaticAwareInterface;
use IfCastle\TypeDefinitions\TypeException;
use IfCastle\TypeDefinitions\Value\ContainerSerializableInterface;

/**
 * ## RemoteException class.
 *
 * Container for exceptions that occurred in external services.
 * The class implements a container for describing exceptional situations in remote services.
 * The final attribute indicates that no other container class is allowed for remote exceptions.
 *
 * The container can properly serialize and deserialize exceptions.
 */
final class RemoteException extends BaseException implements ContainerSerializableInterface, DefinitionStaticAwareInterface, DefinitionAwareInterface
{
    /**
     * @throws DescribeException
     */
    #[\Override]
    public static function definition(): DefinitionMutableInterface
    {
        return new TypeException('RemoteException')
            ->setInstantiableClass(self::class)
            ->asReference();
    }

    /**
     * RemoteException constructor.
     *
     * This constructor has two use cases:
     *
     * If you call the constructor with an argument of type array, you are deserializing a remote exception.
     * If the first parameter is an exception, you are creating a container for transportation.
     *
     * @param \Throwable|array<string, mixed> $exception
     */
    public function __construct(\Throwable|array $exception)
    {
        $previous                   = null;

        if ($exception instanceof \Throwable) {
            $this->isContainer      = true;
            $previous               = $exception;
            $exception              = BaseException::serializeToArray($exception, withTrace: true);

            if (\array_key_exists('previous', $exception)) {

                if (\is_array($exception['previous']) && !empty($exception['previous']['previous'])) {
                    $exception['remotePrevious'] = $exception['previous']['previous'];
                }

                unset($exception['previous']);
            }
        }

        parent::__construct($this->normalizeData($exception), 0, $previous);
    }

    #[\Override]
    public function toArray(bool $withTrace = false): array
    {
        $result                     = parent::toArray($withTrace);

        $result['file']             = $this->getRemoteFile();
        $result['line']             = $this->getRemoteLine();
        $result['source']           = $this->getRemoteSource();
        $result['trace']            = $this->getRemoteTrace();
        $result['remotePrevious']   = $this->getRemotePrevious();

        return $result;
    }


    #[\Override]
    public function getDefinition(): DefinitionInterface
    {
        return self::definition()->asImmutable();
    }

    #[\Override]
    public function containerSerialize(): array|string|bool|int|float|null
    {
        return $this->getDefinition()->encode($this);
    }

    /**
     * @throws EncodingException
     */
    #[\Override]
    public function containerToString(): string
    {
        try {
            return \json_encode($this->getDefinition()->encode($this), JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new EncodingException($this->getDefinition(), 'Failed to encode to JSON: ' . $exception->getMessage());
        }
    }

    public function getRemoteClassName(): string
    {
        return $this->data['type'] ?? '';
    }

    public function getRemoteLine(): int
    {
        return $this->data['line'] ?? 0;
    }

    public function getRemoteFile(): string
    {
        return $this->data['file'] ?? '';
    }

    /**
     * @return array<mixed>
     */
    public function getRemoteTrace(): array
    {
        return $this->data['trace'] ?? [];
    }

    /**
     * @return array<mixed>
     */
    public function getRemoteSource(): array
    {
        return $this->data['source'] ?? [];
    }

    /**
     * @return array<mixed>|null
     */
    public function getRemotePrevious(): array|null
    {
        return $this->data['remotePrevious'] ?? null;
    }

    /**
     * @param array<string, mixed> $exception
     *
     * @return array<string, mixed>
     */
    private function normalizeData(array $exception): array
    {
        if (\array_key_exists('trace', $exception) === false) {
            $exception['trace'] = [];
        }

        if (!\is_array($exception['trace'])) {
            $exception['trace'] = ['The trace key should be array', ['value' => \get_debug_type($exception['trace'])]];
        }

        if (\array_key_exists('source', $exception) === false) {
            $exception['source'] = [];
        }

        if (!\is_array($exception['source'])) {
            $exception['source'] = ['The source key should be array', ['value' => \get_debug_type($exception['source'])]];
        }

        if (\array_key_exists('remotePrevious', $exception) === false) {
            $exception['remotePrevious'] = null;
        }

        if ($exception['remotePrevious'] !== null && !\is_array($exception['remotePrevious'])) {
            $exception['remotePrevious'] = ['The remotePrevious key should be array', ['value' => \get_debug_type($exception['remotePrevious'])]];
        }

        return $exception;
    }
}
