<?php

declare(strict_types=1);

namespace IfCastle\Configurator;

use IfCastle\Application\Bootloader\BootManager\MainConfigAppenderInterface;
use IfCastle\DI\Exceptions\ConfigException;
use IfCastle\Exceptions\RuntimeException;
use IfCastle\OsUtilities\FileSystem\Exceptions\FileIsNotExistException;
use IfCastle\OsUtilities\Safe;

final class ConfigMainAppender extends ConfigIniMutable implements MainConfigAppenderInterface
{
    public function __construct(string $appDir)
    {
        parent::__construct($appDir . '/main.ini');
    }

    /**
     * @throws RuntimeException
     * @throws FileIsNotExistException
     * @throws \ErrorException
     * @throws ConfigException
     */
    #[\Override]
    public function appendSectionIfNotExists(string $section, array $data, string $comment = ''): void
    {
        $this->load();

        $node                       = $this->find(...\explode('.', $section));

        if ($node !== null) {
            return;
        }

        $content                    = [];

        if ($comment !== '') {

            $content[]              = '; ' . \str_repeat('=', 48);

            foreach (\explode("\n", $comment) as $line) {
                $content[]          = '; ' . $line;
            }

            $content[]              = '; ' . \str_repeat('=', 48);
            $content[]              = '';
        }

        $content                    = \array_merge($content, $this->build($data, $section));

        $iniString                  = PHP_EOL . \implode(PHP_EOL, $content);

        Safe::execute(fn() => \file_put_contents($this->file, $iniString, \FILE_APPEND));
        $this->reset();
    }
}
