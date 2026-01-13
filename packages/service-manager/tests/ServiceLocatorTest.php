<?php

declare(strict_types=1);

namespace IfCastle\ServiceManager;

use IfCastle\ServiceManager\Exceptions\ServiceNotFound;
use IfCastle\ServiceManager\ServiceMocks\ServiceLibrary;
use IfCastle\TypeDefinitions\Resolver\ExplicitTypeResolver;
use PHPUnit\Framework\TestCase;

class ServiceLocatorTest extends TestCase
{
    private RepositoryReaderMemory $repositoryReader;

    private ServiceLocatorInterface $serviceLocator;

    #[\Override]
    protected function setUp(): void
    {
        $this->repositoryReader     = RepositoryReaderMemory::buildForTest();
        $this->serviceLocator       = new ServiceLocator(
            new DescriptorRepository($this->repositoryReader, new ExplicitTypeResolver(), new ServiceDescriptorBuilderByReflection())
        );
    }

    public function testFindServiceClass(): void
    {
        $result                     = $this->serviceLocator->findServiceClass('ServiceLibrary');

        $this->assertNotNull($result);
        $this->assertEquals(ServiceLibrary::class, $result);
    }

    public function testFindService(): void
    {
        $result                     = $this->serviceLocator->findService('ServiceLibrary');

        $this->assertNotNull($result);
        $this->assertInstanceOf(ServiceLibrary::class, $result);
    }

    public function testGetService(): void
    {
        $result                     = $this->serviceLocator->getService('ServiceLibrary');

        $this->assertNotNull($result);
        $this->assertInstanceOf(ServiceLibrary::class, $result);
    }

    public function testGetServiceNotFound(): void
    {
        $this->expectException(ServiceNotFound::class);
        $this->serviceLocator->getService('ServiceLibraryNotFound');
    }

    public function testServiceConfig(): void
    {
        $result                     = $this->serviceLocator->getService('ServiceMailer');

        $this->assertNotNull($result);
    }
}
