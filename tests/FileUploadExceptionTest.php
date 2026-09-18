<?php

namespace Arris\Toolkit\Tests;

use Arris\Toolkit\FileUploadException;
use PHPUnit\Framework\TestCase;

class FileUploadExceptionTest extends TestCase
{
    public function testMessageOnly(): void
    {
        $e = new FileUploadException('Upload failed');

        $this->assertSame('Upload failed', $e->getMessage());
        $this->assertSame(0, $e->getCode());
        $this->assertSame([], $e->getErrors());
    }

    public function testWithErrors(): void
    {
        $errors = ['File too large', 'Invalid mime type'];
        $e = new FileUploadException('Validation failed', $errors, 42);

        $this->assertSame('Validation failed', $e->getMessage());
        $this->assertSame(42, $e->getCode());
        $this->assertSame($errors, $e->getErrors());
    }

    public function testWithPrevious(): void
    {
        $previous = new \RuntimeException('Disk error');
        $e = new FileUploadException('Write failed', [], 0, $previous);

        $this->assertSame($previous, $e->getPrevious());
    }

    public function testExtendsException(): void
    {
        $e = new FileUploadException('test');

        $this->assertInstanceOf(\Exception::class, $e);
    }
}
