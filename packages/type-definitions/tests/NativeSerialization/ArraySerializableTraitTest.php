<?php

declare(strict_types=1);

namespace IfCastle\TypeDefinitions\NativeSerialization;

use PHPUnit\Framework\TestCase;

class ArraySerializableTraitTest extends TestCase
{
    public function testSerializeToArray(): void
    {
        $array = [
            'foo' => 'bar',
            'baz' => 'qux',
            'recursive' => [
                'foo' => 'bar',
                'baz' => 'qux',
                'recursive' => [
                    'foo' => 'bar',
                    'baz' => 'qux',
                ],
            ],
        ];

        $object = new class ($array) {
            use ArraySerializableTrait;

            public function __construct(public array $array) {}

            public function toArray(): array
            {
                return $this->serializeToArray($this->array);
            }
        };

        $this->assertEquals($array, $object->toArray());
    }

    public function testUnserializeFromArray(): void
    {
        $array = [
            'foo' => 'bar',
            'baz' => 'qux',
            'recursive' => [
                'foo' => 'bar',
                'baz' => 'qux',
                'recursive' => [
                    'foo' => 'bar',
                    'baz' => 'qux',
                ],
            ],
        ];

        $object = new class ($array) {
            use ArraySerializableTrait;

            public function __construct(public array $array) {}

            public static function fromArray(array $array): static
            {
                return new static(self::unserializeFromArray($array));
            }
        };

        $this->assertEquals($array, $object::fromArray($array)->array);
    }

}
