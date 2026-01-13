<?php

declare(strict_types=1);

namespace IfCastle\DI;

use IfCastle\DI\Exceptions\InjectionNotPossible;

class AttributesToDescriptors
{
    /**
     * @throws \ReflectionException
     * @return DescriptorInterface[]
     */
    public static function readDescriptors(object|string $object, bool $resolveScalarAsConfig = true): array
    {
        $reflection = $object instanceof \ReflectionClass ? $object : new \ReflectionClass($object);

        if (false === $reflection->implementsInterface(InjectableInterface::class)) {

            $constructor            = $reflection->getConstructor();


            if ($constructor === null) {
                return [];
            }

            $descriptors            = [];

            foreach ($constructor->getParameters() as $parameter) {
                $descriptors[]          = self::parameterToDescriptor($reflection, $parameter, $object, $resolveScalarAsConfig);
            }

            return $descriptors;
        }

        $descriptors                = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PUBLIC) as $property) {

            // only DescriptorInterface attributes
            if (empty($property->getAttributes(DescriptorInterface::class, \ReflectionAttribute::IS_INSTANCEOF))) {
                continue;
            }

            $descriptors[]          = self::propertyToDescriptor($reflection, $property, $object, $resolveScalarAsConfig);
        }

        return $descriptors;
    }

    /**
     * @param \ReflectionClass<object>     $reflectionClass
     *
     * @throws InjectionNotPossible
     */
    protected static function parameterToDescriptor(
        \ReflectionClass     $reflectionClass,
        \ReflectionParameter $parameter,
        object|string        $object,
        bool                 $resolveScalarAsConfig = true,
    ): DescriptorInterface {
        $attributes             = $parameter->getAttributes(DescriptorInterface::class, \ReflectionAttribute::IS_INSTANCEOF);

        if ($attributes !== []) {
            $attribute          = $attributes[0];
            $descriptor         = $attribute->newInstance();
            $isNotDefined       = false;
        } else {
            $descriptor         = new Dependency();
            $isNotDefined       = true;
        }

        if ($descriptor instanceof DescriptorInterface === false) {
            throw new \Error('Attribute is not an instance of Dependency');
        }

        if (($descriptorProvider = $descriptor->getDescriptorProvider()) !== null) {
            return $descriptorProvider->provideDescriptor($descriptor, $reflectionClass, $parameter, $object);
        }

        if (false === $descriptor instanceof Dependency) {
            return $descriptor;
        }

        if ($descriptor->key === '') {
            $descriptor->key    = $parameter->getName();
        }

        if ($descriptor->type === null) {
            $descriptor->type   = self::defineType($parameter->getType(), $object);
            self::handleType($descriptor);
        }

        if ($resolveScalarAsConfig && $isNotDefined && self::isScalarType($descriptor->type)) {
            $descriptor         = new FromConfig($parameter->getName(), $descriptor->type);
        }

        self::handleConfigSection($descriptor, $reflectionClass);

        $descriptor->isRequired = false === $parameter->allowsNull() && $parameter->isOptional() === false;

        if ($parameter->getAttributes(Lazy::class) !== []) {
            $descriptor->isLazy = true;
        }

        if ($parameter->isDefaultValueAvailable()) {
            $descriptor->hasDefaultValue    = true;
            $descriptor->defaultValue       = $parameter->getDefaultValue();
        }

        return $descriptor;
    }

    /**
     * @param \ReflectionClass<object>     $reflectionClass
     *
     * @throws InjectionNotPossible
     */
    protected static function propertyToDescriptor(
        \ReflectionClass    $reflectionClass,
        \ReflectionProperty $property,
        object|string       $object,
        bool                $resolveScalarAsConfig = true,
    ): DescriptorInterface {
        $attributes             = $property->getAttributes(DescriptorInterface::class, \ReflectionAttribute::IS_INSTANCEOF);

        if ($attributes !== []) {
            $attribute          = $attributes[0];
            $descriptor         = $attribute->newInstance();
        } else {
            $descriptor         = new Dependency();
        }

        if ($descriptor instanceof DescriptorInterface === false) {
            throw new \Error('Attribute is not an instance of Dependency');
        }

        if (($descriptorProvider = $descriptor->getDescriptorProvider()) !== null) {
            return $descriptorProvider->provideDescriptor($descriptor, $reflectionClass, $property, $object);
        }

        if (false === $descriptor instanceof Dependency) {
            return $descriptor;
        }

        if ($descriptor->key === '') {
            $descriptor->key    = $property->getName();
        }

        if ($descriptor->property === '') {
            $descriptor->property = $property->getName();
        }

        if ($descriptor->type === null) {
            $descriptor->type   = self::defineType($property->getType(), $object);
        }

        if ($resolveScalarAsConfig && false === $descriptor instanceof FromConfig && self::isScalarType($descriptor->type)) {
            $descriptor         = new FromConfig($property->getName(), $descriptor->type);
        }

        self::handleConfigSection($descriptor, $reflectionClass);

        $descriptor->isRequired = false === ($property->hasDefaultValue() || $property->getType()?->allowsNull());

        if ($property->getAttributes(Lazy::class) !== []) {
            $descriptor->isLazy = true;
        }

        if ($property->hasDefaultValue()) {
            $descriptor->hasDefaultValue    = true;
            $descriptor->defaultValue       = $property->getDefaultValue();
        }

        return $descriptor;
    }

    /**
     * @return string|string[]|null
     * @throws InjectionNotPossible
     */
    public static function defineType(mixed $type, object|string $object): string|array|null
    {
        if ($type instanceof \ReflectionUnionType) {
            return self::defineUnionType($type, $object);
        }

        if ($type instanceof \ReflectionNamedType) {
            return self::defineNamedType($type, $object);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return self::defineIntersectionType($type, $object);
        }

        return null;
    }

    /**
     * @throws InjectionNotPossible
     */
    protected static function defineNamedType(\ReflectionNamedType $type, object|string $object): string
    {
        if ($type->isBuiltin()) {
            return match ($type->getName()) {
                'null', 'int', 'float', 'string', 'bool', 'array'
                            => $type->getName(),
                default     => throw new InjectionNotPossible($object, $type->getName(), 'object or scalar type')
            };
        }

        return $type->getName();
    }

    /**
     * @return string[]
     * @throws InjectionNotPossible
     */
    protected static function defineUnionType(\ReflectionUnionType $unionType, object|string $object): array
    {
        $types                  = [];

        foreach ($unionType->getTypes() as $type) {
            if ($type instanceof \ReflectionNamedType) {
                $types[]        = self::defineNamedType($type, $object);
            } else {
                throw new InjectionNotPossible($object, $type::class, 'object');
            }
        }

        return $types;
    }

    /**
     * Returns the first named type from the intersection type.
     *
     * @throws InjectionNotPossible
     */
    protected static function defineIntersectionType(\ReflectionIntersectionType $intersectionType, object|string $object): string
    {
        foreach ($intersectionType->getTypes() as $type) {
            if ($type instanceof \ReflectionNamedType) {
                return self::defineNamedType($type, $object);
            }
        }

        throw new InjectionNotPossible($object, 'intersection type', 'object');
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     */
    protected static function handleConfigSection(mixed $descriptor, \ReflectionClass $reflectionClass): void
    {
        if ($descriptor instanceof FromConfig) {
            $attributes         = $reflectionClass->getAttributes(ConfigSection::class, \ReflectionAttribute::IS_INSTANCEOF);

            if ($attributes !== []) {
                $descriptor->defineSection($attributes[0]->newInstance()->section);
            }
        }
    }

    /**
     * @param string[]|string|null $type
     *
     */
    protected static function isScalarType(array|string|null $type): bool
    {
        if (null === $type) {
            return false;
        }

        if (\is_string($type)) {
            return match ($type) {
                'int', 'float', 'string', 'bool', 'array'
                            => true,
                default     => false
            };
        }

        foreach ($type as $t) {
            if (false === \is_string($t) || false === match ($t) {
                'null', 'int', 'float', 'string', 'bool', 'array'
                            => true,
                default     => false
            }) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws \ReflectionException
     */
    protected static function handleType(Dependency $descriptor): void
    {
        if ($descriptor->type === null) {
            return;
        }

        $types                      = $descriptor->type;

        foreach (\is_string($types) ? [$types] : $types as $type) {

            if (\class_exists($type) === false && \interface_exists($type) === false) {
                continue;
            }

            $reflectionType         = new \ReflectionClass($type);

            $contract               = null;

            foreach (self::iterateByInheritanceForDependencyContract($reflectionType) as $reflection) {
                $attributes         = $reflection->getAttributes(DependencyContract::class, \ReflectionAttribute::IS_INSTANCEOF);

                if ($attributes !== []) {
                    $contract       = $attributes[0]->newInstance();
                    break;
                }
            }

            if ($contract instanceof DependencyContract) {

                //
                // Define a lazy option and providers
                //

                if ($contract->isLazy) {
                    $descriptor->isLazy = true;
                }

                if ($descriptor->getProvider() === null) {
                    $descriptor->provider = $contract->provider;
                }

                if ($descriptor->getDescriptorProvider() === null) {
                    $descriptor->descriptorProvider = $contract->descriptorProvider;
                }

                break;
            }
        }
    }

    /**
     * @param \ReflectionClass<object> $class
     *
     * @return iterable<\ReflectionClass<object>>
     */
    public static function iterateByInheritanceForDependencyContract(\ReflectionClass $class): iterable
    {
        yield $class;

        // iterate by inheritance
        while (true) {

            $current                = $class;

            // iterate by interfaces
            while ($current !== null) {

                // get first interface
                $interfaces         = $current->getInterfaces();

                if ($interfaces === []) {
                    break;
                }

                $current            = \array_first($interfaces);

                yield $current;
            }

            $parent                 = $class->getParentClass();

            if ($parent === false) {
                break;
            }

            yield $parent;

            $class                  = $parent;
        }
    }
}
