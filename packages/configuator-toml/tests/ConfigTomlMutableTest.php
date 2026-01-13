<?php

declare(strict_types=1);

namespace IfCastle\Configurator\Toml;

use PHPUnit\Framework\TestCase;

class ConfigTomlMutableTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (\file_exists('./test.toml')) {
            \unlink('./test.toml');
        }

        // create a new file
        \file_put_contents('./test.toml', '');
    }


    public function testSave(): void
    {
        $config                     = new ConfigTomlMutable('./test.toml');

        $config->set('foo', 'bar');
        $config->set('baz', 'qux');
        $config->save();

        $this->assertFileExists('./test.toml');
        $expected                   = <<<TOML
            foo = "bar"
            baz = "qux"
            TOML;

        $this->assertEquals(
            \str_replace(["\r\n", "\r"], "\n", $expected),
            \str_replace(["\r\n", "\r"], "\n", \file_get_contents('test.toml'))
        );
    }

    public function testSaveNested(): void
    {
        $config                     = new ConfigTomlMutable('./test.toml');

        $config->set('foo.bar', 'baz');
        $config->set('foo.qux', 'quux');
        $config->setSection('foo.nested.section', [
            'key' => 'value',
            'list' => ['item1', 'item2'],
        ]);

        $config->save();

        $this->assertFileExists('./test.toml');
        $expected                   = <<<TOML
            [foo]
            bar = "baz"
            qux = "quux"
            
            [foo.nested]
            [foo.nested.section]
            key = "value"
            list = [ "item1", "item2" ]
            TOML;

        $this->assertEquals(
            \str_replace(["\r\n", "\r"], "\n", $expected),
            \str_replace(["\r\n", "\r"], "\n", \file_get_contents('test.toml'))
        );
    }
}
