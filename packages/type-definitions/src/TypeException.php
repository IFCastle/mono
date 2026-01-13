<?php

declare(strict_types=1);

namespace IfCastle\TypeDefinitions;

use IfCastle\TypeDefinitions\Exceptions\DecodingException;
use IfCastle\TypeDefinitions\Exceptions\DescribeException;
use IfCastle\TypeDefinitions\Exceptions\EncodingException;
use IfCastle\TypeDefinitions\Exceptions\RemoteException;

/**
 * Class TypeException.
 *
 * @package IfCastle\TypeDefinitions
 */
class TypeException extends TypeObject
{
    /**
     * @throws DescribeException
     */
    public function __construct(string $name, bool $isRequired = true, bool $isNullable = false)
    {
        parent::__construct(
            $name,
            $isRequired,
            $isNullable
        );

        $this->type                     = TypesEnum::EXCEPTION->value;

        $this->describe(new TypeString('message')->setDescription('The error message.'))
                ->describe(new TypeString('code')->setDescription('The error code.'))
                ->describe(new TypeString('file')->setDescription('The file in which the error occurred.'))
                ->describe(new TypeInteger('line')->setDescription('The line number on which the error occurred.'))
                ->describe(new TypeJson('trace')->setDescription('The stack trace of the error.'))
                ->describe(new TypeString('class')->setDescription('The class of exception.'))
                ->describe(new TypeString('template', isRequired: false, isNullable: true)
                               ->setDescription('The template of exception.'))
                ->describe(new TypeList('tags', new TypeString('tag'), isRequired: false, isNullable: true)
                               ->setDescription('The tags of the exception.'))
                ->describe(new TypeJson('data', isRequired: false, isNullable: true)
                               ->setDescription('The data of the exception.'))
                ->describe(new TypeSelf('previous')->setDescription('The previous exception.'));
    }

    /**
     * @throws DecodingException
     */
    #[\Override]
    public function decode(float|array|bool|int|string $data): mixed
    {
        if (\is_string($data)) {
            $data                   = $this->jsonDecode($data);
        }

        if (!\is_array($data)) {
            throw new DecodingException($this, 'Expected array. Got {value}', ['value' => \get_debug_type($data)]);
        }

        return new RemoteException($data);
    }

    #[\Override]
    public function encode(mixed $data): mixed
    {
        if ($data instanceof RemoteException) {
            return $data->toArray(true);
        } elseif ($data instanceof \Throwable) {
            return new RemoteException($data)->toArray(true);
        }

        throw new EncodingException($this, 'Expected instance of Throwable', ['value' => \get_debug_type($data)]);

    }
}
