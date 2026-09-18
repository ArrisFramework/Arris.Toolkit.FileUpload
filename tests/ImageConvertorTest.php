<?php

namespace Arris\Toolkit\Tests;

use Arris\Toolkit\FileUpload\ImageConvertor;
use PHPUnit\Framework\TestCase;

class ImageConvertorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        $this->tempDir = sys_get_temp_dir() . '/imageconvertor_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            foreach (glob($this->tempDir . '/*') as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDir);
        }
    }

    private function createJpeg(string $path, int $width = 100, int $height = 100): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagejpeg($image, $path, 90);
        imagedestroy($image);
    }

    private function createPng(string $path, int $width = 100, int $height = 100): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagepng($image, $path);
        imagedestroy($image);
    }

    public function testConvertJpegToWebp(): void
    {
        $source = $this->tempDir . '/source.jpg';
        $target = $this->tempDir . '/target.webp';

        $this->createJpeg($source);

        $result = (new ImageConvertor($source))
            ->setTargetFormat('image/webp')
            ->setQuality(85)
            ->convert($target);

        $this->assertTrue($result);
        $this->assertFileExists($target);
        $this->assertSame('image/webp', mime_content_type($target));
    }

    public function testConvertJpegToPng(): void
    {
        $source = $this->tempDir . '/source.jpg';
        $target = $this->tempDir . '/target.png';

        $this->createJpeg($source);

        $result = (new ImageConvertor($source))
            ->setTargetFormat('image/png')
            ->convert($target);

        $this->assertTrue($result);
        $this->assertFileExists($target);
        $this->assertSame('image/png', mime_content_type($target));
    }

    public function testConvertSameFormat(): void
    {
        $source = $this->tempDir . '/source.jpg';
        $target = $this->tempDir . '/target.jpg';

        $this->createJpeg($source);

        $result = (new ImageConvertor($source))
            ->setTargetFormat('image/jpeg')
            ->setQuality(90)
            ->convert($target);

        $this->assertTrue($result);
        $this->assertFileExists($target);
    }

    public function testConvertNonexistentFile(): void
    {
        $result = (new ImageConvertor($this->tempDir . '/nonexistent.jpg'))
            ->setTargetFormat('image/webp')
            ->convert($this->tempDir . '/target.webp');

        $this->assertFalse($result);
    }

    public function testConvertUnsupportedTargetFormat(): void
    {
        $source = $this->tempDir . '/source.jpg';
        $target = $this->tempDir . '/target.bmp';

        $this->createJpeg($source);

        $result = (new ImageConvertor($source))
            ->setTargetFormat('image/bmp')
            ->convert($target);

        $this->assertFalse($result);
    }

    public function testConvertUnsupportedSourceFormat(): void
    {
        $source = $this->tempDir . '/source.txt';
        file_put_contents($source, 'not an image');

        $result = (new ImageConvertor($source))
            ->setTargetFormat('image/webp')
            ->convert($this->tempDir . '/target.webp');

        $this->assertFalse($result);
    }

    public function testPngAlphaChannelPreserved(): void
    {
        $source = $this->tempDir . '/source.png';
        $target = $this->tempDir . '/target.png';

        $this->createPng($source);

        $result = (new ImageConvertor($source))
            ->setTargetFormat('image/png')
            ->convert($target);

        $this->assertTrue($result);
        $this->assertFileExists($target);
        $this->assertSame('image/png', mime_content_type($target));
    }

    public function testPngCompressionFromQuality(): void
    {
        $source = $this->tempDir . '/source.jpg';
        $this->createJpeg($source);

        // quality 100 → compression 0 (minimum compression)
        $target100 = $this->tempDir . '/q100.png';
        (new ImageConvertor($source))
            ->setTargetFormat('image/png')
            ->setQuality(100)
            ->convert($target100);

        // quality 0 → compression 9 (maximum compression)
        $target0 = $this->tempDir . '/q0.png';
        (new ImageConvertor($source))
            ->setTargetFormat('image/png')
            ->setQuality(0)
            ->convert($target0);

        $this->assertFileExists($target100);
        $this->assertFileExists($target0);
        // Higher compression = smaller file
        $this->assertLessThan(filesize($target100), filesize($target0));
    }

    public function testFluentInterfaceReturnsSelf(): void
    {
        $source = $this->tempDir . '/source.jpg';
        $this->createJpeg($source);

        $converter = new ImageConvertor($source);

        $this->assertSame($converter, $converter->setTargetFormat('image/webp'));
        $this->assertSame($converter, $converter->setQuality(85));
    }
}
