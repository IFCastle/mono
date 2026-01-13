<?php

declare(strict_types=1);

namespace IfCastle\DI;

use IfCastle\DI\Exceptions\DependencyNotFound;

/**
 * @template T
 *
 * This class is used to inject dependencies into the DI container by alias of existing dependency.
 */
final class AliasInitializer implements InitializerInterface
{
    private bool $wasCalled = false;

    /**
     * @var \WeakReference<object>|scalar|array<scalar>|null
     */
    private \WeakReference|int|string|float|bool|array|null $dependency = null;

    public function __construct(public readonly string $alias, public readonly bool $isRequired = false) {}

    #[\Override]
    public function wasCalled(): bool
    {
        return $this->wasCalled;
    }

    /**
     * @return T|null
     * @throws DependencyNotFound
     */
    #[\Override]
    public function executeInitializer(?ContainerInterface $container = null, array $resolvingKeys = []): mixed
    {
        if ($this->wasCalled) {

            if ($this->dependency instanceof \WeakReference) {
                return $this->dependency->get();
            }

            return $this->dependency;
        }

        if (null === $container) {
            return null;
        }

        $this->wasCalled            = true;

        $dependency                 = $this->isRequired ?
                                    $container->resolveDependency($this->alias) :
                                    $container->findDependency($this->alias);

        $this->dependency = \is_object($dependency) ? \WeakReference::create($dependency) : $dependency;

        return $dependency;
    }
}
