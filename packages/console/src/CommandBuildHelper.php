<?php

declare(strict_types=1);

namespace IfCastle\Console;

use IfCastle\Events\Progress\ProgressDispatcherInterface;
use IfCastle\ServiceManager\Exceptions\ServiceException;
use IfCastle\ServiceManager\ServiceDescriptorInterface;
use IfCastle\TypeDefinitions\DefinitionInterface;
use IfCastle\TypeDefinitions\FromEnv;
use IfCastle\TypeDefinitions\FunctionDescriptorInterface;
use IfCastle\TypeDefinitions\TypeInternal;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommandBuildHelper
{
    public static function getCommandName(AsConsole                   $console,
        FunctionDescriptorInterface $methodDescriptor,
        ServiceDescriptorInterface  $serviceDescriptor,
        string                      $serviceName
    ): string {
        $commandName            = $console->commandName;

        if ($commandName === '') {
            $commandName        = $methodDescriptor->getName();
        }

        // Inherit service console attribute if current undefined
        if ($console->namespace === null) {
            $serviceConsole     = $serviceDescriptor->findAttribute(AsConsole::class);

            if ($serviceConsole instanceof AsConsole) {
                $console        = $serviceConsole;
            }
        }

        if ($console->namespace !== null && $console->namespace !== '') {
            $commandName        = $console->namespace . ':' . $commandName;
        } elseif ($console->namespace === null) {
            $commandName        = \lcfirst($serviceName) . ':' . $commandName;
        }

        return $commandName;
    }

    public static function normalizeDefinition(DefinitionInterface $definition): ?string
    {
        if ($definition instanceof TypeInternal === false) {
            return null;
        }

        $type                       = new \ReflectionClass($definition->getTypeName());

        return match (true) {
            $type->implementsInterface(ProgressDispatcherInterface::class) => ProgressDispatcherInterface::class,
            $type->implementsInterface(InputInterface::class) => InputInterface::class,
            $type->implementsInterface(OutputInterface::class) => OutputInterface::class,
            default => null
        };
    }

    /**
     * @return array<string, array{
     *     bool,
     *     string,
     *     DefinitionInterface,
     *     string,
     *     mixed,
     *     bool
     * }>
     * @throws ServiceException
     */
    public static function buildArgumentsAndOptions(FunctionDescriptorInterface $methodDescriptor): array
    {
        $arguments                  = [];

        $formatBooleanName          = static function (string $name): string {

            if (\str_starts_with($name, 'is')) {
                return \lcfirst(\substr($name, 2));
            }

            if (\str_starts_with($name, 'has')) {
                return \lcfirst(\substr($name, 3));
            }

            if (\str_starts_with($name, 'have')) {
                return \lcfirst(\substr($name, 4));
            }

            if (\str_starts_with($name, 'should')) {
                return \lcfirst(\substr($name, 6));
            }

            return $name;

        };

        foreach ($methodDescriptor->getArguments() as $parameter) {

            $definition             = $parameter;
            $type                   = $definition->getTypeName();
            $isInternal             = false;
            $fromEnv                = false;

            if ($parameter->findAttribute(FromEnv::class) !== null) {
                $fromEnv            = true;
            } elseif ((false === $definition->isScalar() && false === $definition->canDecodeFromString())) {

                $normalizedType     = self::normalizeDefinition($definition);

                if (null === $normalizedType && $definition->isRequired()) {
                    throw new ServiceException([
                        'template'  => 'Parameter {parameter} with type {type} can\'t be used with console command for {service}->{command}',
                        'parameter' => $definition->getName(),
                        'service'   => $methodDescriptor->getClassName(),
                        'command'   => $methodDescriptor->getName(),
                        'type'      => $definition->getTypeName(),
                    ]);
                }

                $isInternal         = true;
                $type               = $normalizedType ?? $type;
            }

            // Scalar and boolean parameters
            $arguments[$definition->getName()] = [
                $isInternal,
                $type,
                $definition,
                $type === 'bool' ? $formatBooleanName($definition->getName()) : $definition->getName(),
                $parameter->getDefaultValue(),
                $fromEnv,
            ];
        }

        return $arguments;
    }
}
