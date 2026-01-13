<?php

declare(strict_types=1);

namespace IfCastle\ServiceManager;

use IfCastle\ServiceManager\Exceptions\ServiceException;
use IfCastle\ServiceManager\RepositoryStorages\RepositoryReaderInterface;
use IfCastle\TypeDefinitions\Resolver\ExplicitTypeResolver;
use PHPUnit\Framework\TestCase;

class InternalExecutorTest extends TestCase
{
    protected RepositoryReaderInterface $repositoryReader;

    protected ServiceDescriptorBuilderInterface $descriptorBuilder;

    protected DescriptorRepository $descriptorRepository;

    protected ServiceLocator $serviceLocator;

    protected InternalExecutor $executor;

    #[\Override]
    protected function setUp(): void
    {
        $this->repositoryReader     = RepositoryReaderMemory::buildForTest();
        $this->descriptorBuilder    = new ServiceDescriptorBuilderByReflection();
        $this->descriptorRepository = new DescriptorRepository(
            $this->repositoryReader, new ExplicitTypeResolver(), $this->descriptorBuilder
        );

        $this->serviceLocator       = new ServiceLocator($this->descriptorRepository);
        $this->executor             = new InternalExecutor($this->serviceLocator, $this->serviceLocator);
    }

    /**
     * @throws \Throwable
     * @throws ServiceException
     */
    public function testExecuteCommand(): void
    {
        $this->executor->executeCommand('ServiceLibrary', 'addBook', ['book' => ['author' => 'Author', 'title' => 'Title']]);
        $service = $this->serviceLocator->getService('ServiceLibrary');

        $this->assertCount(3, $service->getBooks());
        $this->assertEquals(['author' => 'Author', 'title' => 'Title'], $service->getBooks()[2]);
    }
}
