<?php

declare(strict_types=1);

namespace IfCastle\Monolog;

use IfCastle\Application\Bootloader\BootloaderContextInterface;
use IfCastle\Application\Bootloader\BootloaderExecutorInterface;
use IfCastle\Application\Bootloader\BootloaderInterface;
use IfCastle\Application\EngineRolesEnum;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

final class Bootloader implements BootloaderInterface
{
    public function buildBootloader(BootloaderExecutorInterface $bootloaderExecutor): void
    {
        $bootloaderExecutor->getBootloaderContext()->getSystemEnvironmentBootBuilder()
            ->bindObject(
                LoggerInterface::class,
                $this->buildLogger($bootloaderExecutor->getBootloaderContext())
            );
    }

    private function buildLogger(BootloaderContextInterface $bootloaderContext): LoggerInterface
    {
        $logger                     = new Logger($bootloaderContext->getApplicationType());
        $configuration              = $bootloaderContext->getApplicationConfig()->findSection('logger');
        $roles                      = $bootloaderContext->getExecutionRoles();

        if (\in_array(EngineRolesEnum::CONSOLE->value, $roles, true)) {
            $logHandler             = new StreamHandler('php://stdout');
            $logHandler->pushProcessor(new PsrLogMessageProcessor());
            $logHandler->setFormatter(new LineFormatter());

            $logger->pushHandler($logHandler);
        }

        return $logger;
    }
}
