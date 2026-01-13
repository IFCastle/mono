<?php

declare(strict_types=1);

namespace IfCastle\Configurator;

use PHPUnit\Framework\TestCase;

class ConfigIniMutableTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (\file_exists('./test.ini')) {
            \unlink('./test.ini');
        }

        // create a new file
        \file_put_contents('./test.ini', '');
    }


    public function testSave(): void
    {
        $config                     = new ConfigIniMutable('./test.ini');

        $config->set('foo', 'bar');
        $config->set('baz', 'qux');
        $config->save();

        $this->assertFileExists('./test.ini');
        $expected                   = <<<INI
            foo = "bar"
            baz = "qux"
            INI;

        $this->assertEquals(
            \str_replace(["\r\n", "\r"], "\n", $expected),
            \str_replace(["\r\n", "\r"], "\n", \file_get_contents('test.ini'))
        );
    }

    public function testSaveNested(): void
    {
        $config                     = new ConfigIniMutable('./test.ini');

        $config->set('foo.bar', 'baz');
        $config->set('foo.qux', 'quux');
        $config->setSection('foo.nested.section', [
            'key' => 'value',
            'list' => ['item1', 'item2'],
        ]);

        $config->save();

        $this->assertFileExists('./test.ini');
        $expected                   = <<<INI

            ;----------------------------------------
            [foo]
            bar = "baz"
            qux = "quux"

            ;----------------------------------------
            [foo.nested.section]
            key = "value"
            list[] = "item1"
            list[] = "item2"
            INI;

        $this->assertEquals(
            \str_replace(["\r\n", "\r"], "\n", $expected),
            \str_replace(["\r\n", "\r"], "\n", \file_get_contents('test.ini'))
        );
    }
}
