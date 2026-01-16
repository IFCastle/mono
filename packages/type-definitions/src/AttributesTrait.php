<?php

declare(strict_types=1);

namespace IfCastle\TypeDefinitions;

use IfCastle\TypeDefinitions\NativeSerialization\AttributeNameInterface;

trait AttributesTrait
{
    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    #[\Override]
    public function getAttributes(?string $name = null): array
    {
        if ($name === null) {
            return $this->attributes;
        }

        $attributes                 = [];

        foreach ($this->attributes as $attribute) {
            /* @phpstan-ignore-next-line */
            if (\is_subclass_of($attribute, $name) || ($attribute instanceof AttributeNameInterface && $attribute->getAttributeName() === $name)) {
                $attributes[]       = $attribute;
            }
        }

        return $attributes;
    }

    #[\Override]
    public function findAttribute(string $name): object|null
    {
        foreach ($this->attributes as $attribute) {
            /* @phpstan-ignore-next-line */
            if (\is_subclass_of($attribute, $name) || ($attribute instanceof AttributeNameInterface && $attribute->getAttributeName() === $name)) {
                return $attribute;
            }
        }

        return null;
    }
}
