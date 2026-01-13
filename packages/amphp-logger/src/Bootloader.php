<?php

declare(strict_types=1);

namespace IfCastle\Amphp\Logger;

use Amp\ByteStream;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use IfCastle\Application\Bootloader\BootloaderContextInterface;
use IfCastle\Application\Bootloader\BootloaderExecutorInterface;
use IfCastle\Application\Bootloader\BootloaderInterface;
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

        $logHandler                 = new StreamHandler(ByteStream\getStdout());
        $logHandler->pushProcessor(new PsrLogMessageProcessor());
        $logHandler->setFormatter(new ConsoleFormatter());

        $logger->pushHandler($logHandler);

        return $logger;
    }
}
