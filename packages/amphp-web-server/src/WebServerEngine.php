<?php

declare(strict_types=1);

namespace IfCastle\AmphpWebServer;

use IfCastle\AmpPool\WorkerGroup as AmpWorkerGroup;
use IfCastle\AmpPool\WorkerPool;
use IfCastle\AmpPool\WorkerTypeEnum as WorkerPoolTypeEnum;
use IfCastle\Application\Console\ConsoleLoggerInterface;
use IfCastle\Application\Environment\SystemEnvironmentInterface;
use IfCastle\Application\WorkerPool\WorkerGroup;
use IfCastle\Application\WorkerPool\WorkerGroupInterface;
use IfCastle\Application\WorkerPool\WorkerPoolBuilderInterface;
use IfCastle\Application\WorkerPool\WorkerPoolInterface;
use IfCastle\Application\WorkerPool\WorkerState;
use IfCastle\Application\WorkerPool\WorkerStateInterface;
use IfCastle\Application\WorkerPool\WorkerTypeEnum;
use IfCastle\DI\ConfigInterface;
use IfCastle\DI\Dependency;
use IfCastle\OsUtilities\Safe;

class WebServerEngine extends \IfCastle\Amphp\AmphpEngine implements WorkerPoolBuilderInterface, WorkerPoolInterface
{
    /**
     * @var AmpWorkerGroup[]
     */
    protected array $ampWorkerGroups = [];

    /**
     * @var \IfCastle\AmpPool\WorkerPool<object, object>|null
     */
    protected WorkerPool|null $workerPool = null;

    public function __construct(
        ConfigInterface                         $configuration,
        #[Dependency]
        protected string                        $applicationDir,
        protected readonly ConsoleLoggerInterface|null $logger = null
    ) {
        $this->applyConfiguration($configuration->findSection('server'));
    }

    #[\Override]
    public function start(): void
    {
        if ($this->workerPool !== null) {
            return;
        }

        // Change directory to the application directory
        if (\getcwd() !== $this->applicationDir && false === Safe::execute(fn() => \chdir($this->applicationDir))) {
            throw new \RuntimeException('Unable to change directory to ' . $this->applicationDir);
        }

        $this->workerPool       = new WorkerPool(logger: $this->logger);
        $this->workerPool->setPoolContext([SystemEnvironmentInterface::APPLICATION_DIR => $this->applicationDir]);

        foreach ($this->ampWorkerGroups as $group) {
            $this->workerPool->describeGroup($group);
        }

        $helloMessage               = 'IFCastle AMPHP Web Server';

        // Check JIT support
        if (\function_exists('opcache_get_status')) {
            $opcacheStatus          = \opcache_get_status(false);
            $jitStatus              = $opcacheStatus['jit'] ?? false;

            if ($jitStatus) {
                $helloMessage       .= ' with JIT';
            }
        }

        $this->logger->info($helloMessage, [ConsoleLoggerInterface::IN_FRAME => true, ConsoleLoggerInterface::VERSION => '1.0.0']);

        $this->workerPool->run();
    }

    #[\Override]
    public function describeGroup(WorkerGroupInterface $group): void
    {
        // Convert WorkerGroupInterface to AmpWorkerGroup
        $this->ampWorkerGroups[] = new AmpWorkerGroup(
            $group->getEntryPointClass(),
            $this->appWorkerTypeToWorkerPoolType($group->getWorkerType()),
            $group->getMinWorkers(),
            $group->getMaxWorkers(),
            $group->getGroupName()
        );
    }

    #[\Override]
    public function getAllWorkerState(): array
    {
        $workerStates               = [];

        foreach ($this->workerPool->getWorkersStorage()->foreachWorkers() as $workerState) {
            $workerStates[]         = new WorkerState(
                workerId: $workerState->getWorkerId(),
                groupId: $workerState->getGroupId(),
                shouldBeStarted: $workerState->isShouldBeStarted(),
                pid: $workerState->getPid()
            );
        }

        return $workerStates;
    }

    #[\Override]
    public function getWorkerState(int $workerId): WorkerStateInterface
    {
        $workerState                = $this->workerPool->getWorkersStorage()->getWorkerState($workerId);

        return new WorkerState(
            workerId: $workerState->getWorkerId(),
            groupId: $workerState->getGroupId(),
            shouldBeStarted: $workerState->isShouldBeStarted(),
            pid: $workerState->getPid()
        );
    }

    #[\Override]
    public function getWorkerGroups(): array
    {
        $workerGroups               = [];

        foreach ($this->workerPool->getGroupsScheme() as $group) {
            $workerGroups[]         = new WorkerGroup(
                $group->getEntryPointClass(),
                $this->workerPoolTypeToAppWorkerType($group->getWorkerType()),
                $group->getMinWorkers(),
                $group->getMaxWorkers(),
                $group->getGroupName()
            );
        }

        return $workerGroups;
    }

    #[\Override]
    public function findGroup(int|string $groupIdOrName): WorkerGroupInterface|null
    {
        $group                     = $this->workerPool->findGroup($groupIdOrName);

        if ($group === null) {
            return null;
        }

        return new WorkerGroup(
            $group->getEntryPointClass(),
            $this->workerPoolTypeToAppWorkerType($group->getWorkerType()),
            $group->getMinWorkers(),
            $group->getMaxWorkers(),
            $group->getGroupName()
        );
    }

    #[\Override]
    public function isWorkerRunning(int $workerId): bool
    {
        return $this->workerPool->isWorkerRunning($workerId);
    }

    #[\Override]
    public function restartWorker(int $workerId): bool
    {
        return $this->workerPool->restartWorker($workerId);
    }

    private function workerPoolTypeToAppWorkerType(WorkerPoolTypeEnum $workerType): WorkerTypeEnum
    {
        return match ($workerType) {
            WorkerPoolTypeEnum::REACTOR => WorkerTypeEnum::REACTOR,
            WorkerPoolTypeEnum::JOB     => WorkerTypeEnum::JOB,
            WorkerPoolTypeEnum::SERVICE => WorkerTypeEnum::SERVICE,
        };
    }

    private function appWorkerTypeToWorkerPoolType(WorkerTypeEnum $workerType): WorkerPoolTypeEnum
    {
        return match ($workerType) {
            WorkerTypeEnum::REACTOR => WorkerPoolTypeEnum::REACTOR,
            WorkerTypeEnum::JOB     => WorkerPoolTypeEnum::JOB,
            WorkerTypeEnum::SERVICE => WorkerPoolTypeEnum::SERVICE,
        };
    }

    /**
     * @param array<string, mixed>|null $config
     */
    private function applyConfiguration(array|null $config = null): void
    {
        if ($config === null) {
            return;
        }

        $reactors                   = $config['reactors'] ?? 1;
        $jobs                       = $config['jobs'] ?? 1;

        $reactors                   = (int) $reactors;
        $jobs                       = (int) $jobs;

        if ($reactors > 0) {
            $this->describeGroup(new WorkerGroup(
                HttpReactor::class,
                WorkerTypeEnum::REACTOR,
                $reactors,
                0,
                'Reactors'
            ));
        }

        if ($jobs > 0) {

            if (false === \interface_exists('IfCastle\ServiceManager\ExecutorInterface')) {
                throw new \RuntimeException('Job-Worker is not available'
                                            . ' because the service manager is not installed! '
                                            . 'The IfCastle\ServiceManager\ExecutorInterface interface is not available. '
                                            . 'Please make sure that the "ifcastle/service-manager" package is installed.'
                );
            }

            $this->describeGroup(new WorkerGroup(
                JobWorker::class,
                WorkerTypeEnum::JOB,
                $jobs,
                0,
                'Jobs'
            ));
        }
    }
}
