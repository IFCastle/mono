<?php

declare(strict_types=1);

namespace IfCastle\ServiceManager;

use IfCastle\ServiceManager\Exceptions\ServiceNotFound;
use IfCastle\ServiceManager\ServiceMocks\ServiceLibrary;
use IfCastle\ServiceManager\ServiceMocks\ServiceLibraryPublic;
use IfCastle\TypeDefinitions\Resolver\ExplicitTypeResolver;
use PHPUnit\Framework\TestCase;

class ServiceLocatorWithInheritanceTest extends TestCase
{
    private RepositoryReaderMemory $repositoryReader;

    private ServiceLocatorInterface $serviceLocator;

    #[\Override]
    protected function setUp(): void
    {
        $this->repositoryReader     = RepositoryReaderMemory::buildForPublicTest();
        $this->serviceLocator       = new ServiceLocatorWithInheritance(
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

    public function testGetPublicServiceList(): void
    {
        $serviceLocatorPublicInternal = new ServiceLocatorPublicInternal($this->serviceLocator);

        $result                     = $serviceLocatorPublicInternal->getPublicServiceList();

        $this->assertArrayHasKey('ServiceLibraryPublic', $result);
    }

    public function testFindPublicService(): void
    {
        $serviceLocatorPublicInternal = new ServiceLocatorPublicInternal($this->serviceLocator);

        $result                     = $serviceLocatorPublicInternal->findPublicService('ServiceLibraryPublic');

        $this->assertNotNull($result);
        $this->assertInstanceOf(ServiceLibraryPublic::class, $result);
    }

    public function testFindPublicServiceNotFound(): void
    {
        $serviceLocatorPublicInternal = new ServiceLocatorPublicInternal($this->serviceLocator);

        $result                     = $serviceLocatorPublicInternal->findPublicService('ServiceLibrary');

        $this->assertNull($result);
    }

    public function testGetPublicService(): void
    {
        $serviceLocatorPublicInternal = new ServiceLocatorPublicInternal($this->serviceLocator);

        $result                     = $serviceLocatorPublicInternal->getPublicService('ServiceLibraryPublic');

        $this->assertNotNull($result);
        $this->assertInstanceOf(ServiceLibraryPublic::class, $result);
    }

    public function testGetPublicServiceNotFound(): void
    {
        $this->expectException(ServiceNotFound::class);

        $serviceLocatorPublicInternal = new ServiceLocatorPublicInternal($this->serviceLocator);

        $serviceLocatorPublicInternal->getPublicService('ServiceLibrary');
    }

    public function testGetPublicServiceNotFound2(): void
    {
        $this->expectException(ServiceNotFound::class);

        $serviceLocatorPublicInternal = new ServiceLocatorPublicInternal($this->serviceLocator);

        $serviceLocatorPublicInternal->getPublicService('ServiceLibraryNotFound');
    }

    public function testGetServiceDescriptorList(): void
    {
        $result                     = $this->serviceLocator->getServiceDescriptorList();

        $this->assertArrayHasKey('ServiceLibrary', $result);
        $this->assertArrayHasKey('ServiceMailer', $result);
    }

    public function testFindServiceDescriptor(): void
    {
        $result                     = $this->serviceLocator->findServiceDescriptor('ServiceLibrary');

        $this->assertNotNull($result);
        $this->assertEquals('ServiceLibrary', $result->getServiceName());
    }

    public function testFindServiceDescriptorNotFound(): void
    {
        $result                     = $this->serviceLocator->findServiceDescriptor('ServiceLibraryNotFound');

        $this->assertNull($result);
    }

    public function testGetServiceDescriptor(): void
    {
        $result                     = $this->serviceLocator->getServiceDescriptor('ServiceLibrary');

        $this->assertNotNull($result);
        $this->assertEquals('ServiceLibrary', $result->getServiceName());
    }

    public function testGetServiceDescriptorNotFound(): void
    {
        $this->expectException(ServiceNotFound::class);

        $this->serviceLocator->getServiceDescriptor('ServiceLibraryNotFound');
    }
}
