<?php

declare(strict_types=1);

namespace IfCastle\TypeDefinitions\Reader;

use IfCastle\TypeDefinitions\DefinitionInterface;
use IfCastle\TypeDefinitions\Resolver\ExplicitTypeResolver;
use IfCastle\TypeDefinitions\Resolver\TypeContext;
use IfCastle\TypeDefinitions\TypeAllOf;
use IfCastle\TypeDefinitions\TypeBool;
use IfCastle\TypeDefinitions\TypeFloat;
use IfCastle\TypeDefinitions\TypeInteger;
use IfCastle\TypeDefinitions\TypeJson;
use IfCastle\TypeDefinitions\TypeObject;
use IfCastle\TypeDefinitions\TypeOneOf;
use IfCastle\TypeDefinitions\TypesEnum;
use IfCastle\TypeDefinitions\TypeString;
use IfCastle\TypeDefinitions\TypeVoid;
use PHPUnit\Framework\TestCase;

class ReflectionTypeReaderTest extends TestCase
{
    public function testGenerateParameterNativeTypes(): void
    {
        $class                      = new class {
            public function test(int $integer, float $float, bool $boolean, array $array, string $string): void {}
        };

        $reflectionMethod           = new \ReflectionMethod($class, 'test');

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $reflectionTypeReader   = new ReflectionTypeReader(
                $parameter,
                new TypeContext($class::class, $reflectionMethod->getName(), $parameter->getName(), isParameter: true),
                new ExplicitTypeResolver()
            );

            $definition             = $reflectionTypeReader->generate();

            $this->assertNotNull($definition, 'Definition is null');
            $this->assertEquals($parameter->getName(), $definition->getName());

            switch ($parameter->getType()->getName()) {
                case 'int':
                    $this->assertEquals(TypesEnum::INTEGER->value, $definition->getTypeName());
                    $this->assertInstanceOf(TypeInteger::class, $definition);
                    break;
                case 'float':
                    $this->assertEquals(TypesEnum::FLOAT->value, $definition->getTypeName());
                    $this->assertInstanceOf(TypeFloat::class, $definition);
                    break;
                case 'bool':
                    $this->assertEquals(TypesEnum::BOOL->value, $definition->getTypeName());
                    $this->assertInstanceOf(TypeBool::class, $definition);
                    break;
                case 'array':
                    $this->assertEquals(TypesEnum::ARRAY->value, $definition->getTypeName());
                    $this->assertInstanceOf(TypeJson::class, $definition);
                    break;
                case 'string':
                    $this->assertEquals(TypesEnum::STRING->value, $definition->getTypeName());
                    $this->assertInstanceOf(TypeString::class, $definition);
                    break;
            }
        }
    }

    public function testGeneratePropertyNativeTypes(): void
    {
        $class                      = new class {
            protected int $integer;

            protected float $float;

            protected bool $boolean;

            protected array $array;

            protected string $string;
        };

        $reflectionClass           = new \ReflectionClass($class);

        foreach ($reflectionClass->getProperties() as $property) {
            $reflectionTypeReader   = new ReflectionTypeReader(
                $property,
                new TypeContext($class::class, $reflectionClass->getName(), propertyName: $property->getName(), isProperty: true),
                new ExplicitTypeResolver()
            );

            $definition             = $reflectionTypeReader->generate();

            $this->assertNotNull($definition, 'Definition is null');
            $this->assertEquals($property->getName(), $definition->getName());

            switch ($property->getType()->getName()) {
                case 'int':
                    $this->assertEquals(TypesEnum::INTEGER->value, $definition->getTypeName());
                    $this->assertInstanceOf(TypeInteger::class, $definition);
                    break;
                case 'float':
                    $this->assertEquals(TypesEnum::FLOAT->value, $definition->getTypeName());
                    $this->assertInstanceOf(TypeFloat::class, $definition);
                    break;
                case 'bool':
                    $this->assertEquals(TypesEnum::BOOL->value, $definition->getTypeName());
                    $this->assertInstanceOf(TypeBool::class, $definition);
                    break;
                case 'array':
                    $this->assertEquals(TypesEnum::ARRAY->value, $definition->getTypeName());
                    $this->assertInstanceOf(TypeJson::class, $definition);
                    break;
                case 'string':
                    $this->assertEquals(TypesEnum::STRING->value, $definition->getTypeName());
                    $this->assertInstanceOf(TypeString::class, $definition);
                    break;
            }
        }
    }

    public function testGenerateVoid(): void
    {
        $class                      = new class {
            public function test(): void {}
        };

        $reflectionMethod           = new \ReflectionMethod($class, 'test');

        $reflectionTypeReader       = new ReflectionTypeReader(
            $reflectionMethod->getReturnType(),
            new TypeContext($class::class, $reflectionMethod->getName(), isReturnType: true),
            new ExplicitTypeResolver()
        );

        $definition                 = $reflectionTypeReader->generate();

        $this->assertNotNull($definition, 'Definition is null');
        $this->assertEquals('void', $definition->getTypeName());
        $this->assertInstanceOf(TypeVoid::class, $definition);
    }

    public function testGenerateUnionType(): void
    {
        $class                      = new class {
            public function test(int|string|null $value): void {}
        };

        $reflectionMethod           = new \ReflectionMethod($class, 'test');

        $reflectionTypeReader       = new ReflectionTypeReader(
            $reflectionMethod->getParameters()[0],
            new TypeContext($class::class, $reflectionMethod->getName(), $reflectionMethod->getParameters()[0]->getName(), isParameter: true),
            new ExplicitTypeResolver()
        );

        $definition                 = $reflectionTypeReader->generate();

        $this->assertNotNull($definition, 'Definition is null');
        $this->assertInstanceOf(TypeOneOf::class, $definition);
        $this->assertEquals(TypesEnum::ONE_OF->value, $definition->getTypeName());
        $this->assertEquals(
            [TypesEnum::STRING->value, TypesEnum::INTEGER->value, TypesEnum::NULL->value],
            \array_map(fn(DefinitionInterface $type) => $type->getTypeName(), $definition->getCases())
        );
    }

    public function testGenerateInterface(): void
    {
        $class                      = new class {
            public function test(AInterface $value): void {}
        };

        $reflectionMethod           = new \ReflectionMethod($class, 'test');

        $reflectionTypeReader       = new ReflectionTypeReader(
            $reflectionMethod->getParameters()[0],
            new TypeContext($class::class, $reflectionMethod->getName(), $reflectionMethod->getParameters()[0]->getName(), isParameter: true),
            new ExplicitTypeResolver()
        );

        $definition                 = $reflectionTypeReader->generate();

        $this->assertNotNull($definition, 'Definition is null');
        $this->assertInstanceOf(TypeObject::class, $definition);
    }

    public function testGenerateIntersectionType(): void
    {
        $class                      = new class {
            public function test(AInterface & BInterface $value): void {}
        };

        $reflectionMethod           = new \ReflectionMethod($class, 'test');

        $reflectionTypeReader       = new ReflectionTypeReader(
            $reflectionMethod->getParameters()[0],
            new TypeContext($class::class, $reflectionMethod->getName(), $reflectionMethod->getParameters()[0]->getName(), isParameter: true),
            new ExplicitTypeResolver()
        );

        $definition                 = $reflectionTypeReader->generate();

        $this->assertNotNull($definition, 'Definition is null');
        $this->assertInstanceOf(TypeAllOf::class, $definition);
        $this->assertEquals('allOf', $definition->getTypeName());
        $this->assertInstanceOf(TypeObject::class, $definition->getCases()[0]);
    }
}
