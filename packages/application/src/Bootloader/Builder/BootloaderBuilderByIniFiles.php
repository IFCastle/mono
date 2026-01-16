<?php

declare(strict_types=1);

namespace IfCastle\Application\Bootloader\Builder;

use IfCastle\Application\Bootloader\BootManager\BootManagerApplication;
use IfCastle\DI\ConfigInterface;

final class BootloaderBuilderByIniFiles extends BootloaderBuilderAbstract
{
    /**
     * @param string[] $runtimeTags
     */
    public function __construct(
        protected string $appDirectory,
        private readonly string   $bootloaderDir,
        protected string $applicationType,
        protected array  $runtimeTags
    ) {}

    #[\Override]
    protected function initConfigurator(): ConfigInterface
    {
        $configuratorFile           = $this->bootloaderDir . '/' . BootManagerApplication::CONFIGURATOR . '.ini';

        if (false === \file_exists($configuratorFile)) {
            throw new \RuntimeException('Configuration component not found ('.$configuratorFile.').'
                                        . ' Try installing one of the following: '
                                        . '"composer require ifcastle/configurator-ini"');
        }

        $bootloaderConfigs          = $this->read($configuratorFile);

        if (empty($bootloaderConfigs)) {
            throw new \RuntimeException('Bootloader can\'t access to configurator file: ' . $configuratorFile . ' or it is empty');
        }

        $configuratorClass          = $this->getFirstBootloaderClass($bootloaderConfigs);

        if (empty($configuratorClass) || false === \class_exists($configuratorClass)) {
            throw new \RuntimeException('Configurator class not found: ' . $configuratorClass);
        }

        $configurator              = new $configuratorClass();

        if (false === $configurator instanceof ConfigInterface) {
            throw new \RuntimeException('Configurator class must implement ConfigInterface: ' . $configuratorClass);
        }

        if ($configurator instanceof ZeroContextRequiredInterface) {
            $configurator->setZeroContext($this);
        }

        return $configurator;
    }

    #[\Override]
    protected function fetchBootloaders(): iterable
    {
        foreach (\glob($this->bootloaderDir . '/*.ini') as $file) {

            if (\str_ends_with($file, 'configurator.ini')) {
                continue;
            }

            $bootloaders            = $this->read($file);

            if (null === $bootloaders || $bootloaders === []) {
                continue;
            }

            yield from $this->walkByBootloaderConfig($bootloaders);
        }
    }

    /**
     * @param array<array<string, scalar|scalar[]|null>> $bootloaders
     *
     */
    protected function getFirstBootloaderClass(array $bootloaders): string|null
    {
        foreach ($this->walkByBootloaderConfig($bootloaders) as $bootloaderClass) {
            return $bootloaderClass;
        }

        return null;
    }

    /**
     * @param array<array<string, scalar|scalar[]|null>> $bootloaders
     *
     * @return iterable<class-string>
     */
    protected function walkByBootloaderConfig(array $bootloaders): iterable
    {
        foreach ($bootloaders as $bootloader) {

            if (!\is_array($bootloader)) {
                continue;
            }

            if (\array_key_exists('isActive', $bootloader) === false || empty($bootloader['isActive'])) {
                continue;
            }

            if (\array_key_exists('forApplication', $bootloader)
               && \is_array($bootloader['forApplication'])
               && \in_array($this->applicationType, $bootloader['forApplication']) === false) {
                continue;
            }

            if (\array_key_exists('bootloader', $bootloader) === false || empty($bootloader['bootloader'])) {
                continue;
            }

            foreach ($bootloader['bootloader'] as $bootloaderClass) {
                yield $bootloaderClass;
            }
        }
    }

    /**
     *
     * @return array<array<string, scalar|scalar[]|null>>|null
     */
    protected function read(string $file): array|null
    {
        $data                       = \parse_ini_file($file, true);

        if (false === \is_array($data)) {
            return null;
        }

        return $data;
    }
}
