<?php

declare(strict_types=1);

namespace IfCastle\Configurator\Toml;

use PHPUnit\Framework\TestCase;

class ConfigMainAppenderTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (\file_exists('./main.toml')) {
            \unlink('./main.toml');
        }

        // create a new file
        \file_put_contents('./main.toml', '');
    }

    public function testAppendSectionIfNotExists(): void
    {
        $config                     = new ConfigMainAppender(__DIR__);
        $config->appendSectionIfNotExists('main', [
            'foo' => 'bar',
            'baz' => 'qux',
        ], "My comment\nMy comment 2");

        $this->assertFileExists(__DIR__ . '/main.toml');
        $expected                   = <<<TOML
            
            # ================================================
            # My comment
            # My comment 2
            # ================================================
            [main]
            foo = "bar"
            baz = "qux"
            TOML;

        $this->assertEquals(
            \str_replace(["\r\n", "\r"], "\n", $expected),
            \str_replace(["\r\n", "\r"], "\n", \file_get_contents(__DIR__ . '/main.toml'))
        );
    }
}
