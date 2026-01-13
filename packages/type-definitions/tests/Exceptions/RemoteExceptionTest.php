<?php

declare(strict_types=1);

namespace IfCastle\TypeDefinitions\Exceptions;

use PHPUnit\Framework\TestCase;

class RemoteExceptionTest extends TestCase
{
    public function testToArray(): void
    {
        $line                       = __LINE__ + 1;
        $exception                  = new \Exception('Test exception');
        $remoteException            = new RemoteException($exception);

        $expected =  [
            'type' => 'Exception',
            'source' =>
                 [
                     'source' => self::class,
                     'type' => '->',
                     'function' => __FUNCTION__,
                 ],
            'file' => __FILE__,
            'line' => $line,
            'message' => 'Test exception',
            'code' => 0,
            'data' => [],
            'previous' => null,
            'remotePrevious' => null,
            'container' => RemoteException::class,
        ];

        $serialized = $remoteException->toArray(true);

        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('trace', $serialized, 'The trace key is missing');
        unset($serialized['trace']);

        $this->assertEquals($expected, $serialized, 'The serialized array does not match the expected array');
    }

    public function testRestore(): void
    {
        $line                       = __LINE__ + 1;

        $remoteException            = [
            'type'                  => 'Exception',
            'source'                =>
                [
                    'source'        => self::class,
                    'type'          => '->',
                    'function'      => __FUNCTION__,
                ],
            'file'                  => __FILE__,
            'line'                  => $line,
            'message'               => 'Test exception',
            'code'                  => 500,
            'data'                  => [],
            'previous'              => null,
            'remotePrevious'        => null,
            'container'             => RemoteException::class,
        ];

        $restored                   = new RemoteException($remoteException);

        $this->assertSame('Exception', $restored->getRemoteClassName(), 'The remote class name does not match');
        $this->assertSame($line, $restored->getRemoteLine(), 'The remote line does not match');
        $this->assertSame(__FILE__, $restored->getRemoteFile(), 'The remote file does not match');
        $this->assertSame('Test exception', $restored->getMessage(), 'The remote message does not match');
        $this->assertSame(500, $restored->getCode(), 'The remote code does not match');
    }
}
