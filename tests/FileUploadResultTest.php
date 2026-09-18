<?php

namespace Arris\Toolkit\Tests;

use Arris\Toolkit\FileUploadResult;
use PHPUnit\Framework\TestCase;

class FileUploadResultTest extends TestCase
{
    public function testConstants(): void
    {
        $this->assertSame('uploaded', FileUploadResult::STAGE_UPLOADED);
        $this->assertSame('processed', FileUploadResult::STAGE_PROCESSED);
    }

    public function testSuccessResult(): void
    {
        $result = new FileUploadResult(
            isSuccess: true,
            stage: FileUploadResult::STAGE_PROCESSED,
            originalName: 'photo.jpg',
            savedName: '2024_01_15__abc.jpg',
            path: '/var/www/uploads/',
            fullPath: '/var/www/uploads/2024_01_15__abc.jpg',
            mimeType: 'image/jpeg',
            size: 102400,
            radix: '2024_01_15__abc',
            extension: 'jpg',
            width: 1920,
            height: 1080,
            tmpName: '/tmp/phpABC123',
            relativePath: 'uploads/image.jpg'
        );

        $this->assertTrue($result->isSuccess);
        $this->assertSame('processed', $result->stage);
        $this->assertSame('photo.jpg', $result->originalName);
        $this->assertSame('2024_01_15__abc.jpg', $result->savedName);
        $this->assertSame('/var/www/uploads/', $result->path);
        $this->assertSame('/var/www/uploads/2024_01_15__abc.jpg', $result->fullPath);
        $this->assertSame('image/jpeg', $result->mimeType);
        $this->assertSame(102400, $result->size);
        $this->assertSame('2024_01_15__abc', $result->radix);
        $this->assertSame('jpg', $result->extension);
        $this->assertSame(1920, $result->width);
        $this->assertSame(1080, $result->height);
        $this->assertSame('/tmp/phpABC123', $result->tmpName);
        $this->assertSame('uploads/image.jpg', $result->relativePath);
        $this->assertNull($result->lastError);
        $this->assertSame([], $result->errors);
    }

    public function testFailureResult(): void
    {
        $result = new FileUploadResult(
            isSuccess: false,
            stage: FileUploadResult::STAGE_UPLOADED,
            originalName: 'bad.exe',
            lastError: 'Недопустимый тип файла',
            errors: ['Недопустимый тип файла: application/x-executable']
        );

        $this->assertFalse($result->isSuccess);
        $this->assertSame('uploaded', $result->stage);
        $this->assertSame('bad.exe', $result->originalName);
        $this->assertSame('Недопустимый тип файла', $result->lastError);
        $this->assertCount(1, $result->errors);
        $this->assertNull($result->savedName);
        $this->assertNull($result->fullPath);
        $this->assertNull($result->size);
        $this->assertNull($result->width);
        $this->assertNull($result->height);
        $this->assertNull($result->tmpName);
        $this->assertNull($result->relativePath);
    }

    public function testToArray(): void
    {
        $result = new FileUploadResult(
            isSuccess: true,
            stage: FileUploadResult::STAGE_PROCESSED,
            originalName: 'test.png',
            savedName: 'generated.png',
            path: '/uploads/',
            fullPath: '/uploads/generated.png',
            mimeType: 'image/png',
            size: 2048,
            radix: 'generated',
            extension: 'png',
            width: 800,
            height: 600,
            tmpName: '/tmp/phpDEF456',
            relativePath: 'photos/pic.jpg'
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertTrue($array['isSuccess']);
        $this->assertSame('processed', $array['stage']);
        $this->assertSame('test.png', $array['originalName']);
        $this->assertSame('generated.png', $array['savedName']);
        $this->assertSame('/uploads/', $array['path']);
        $this->assertSame('/uploads/generated.png', $array['fullPath']);
        $this->assertSame('image/png', $array['mimeType']);
        $this->assertSame(2048, $array['size']);
        $this->assertSame('generated', $array['radix']);
        $this->assertSame('png', $array['extension']);
        $this->assertSame(800, $array['width']);
        $this->assertSame(600, $array['height']);
        $this->assertSame('/tmp/phpDEF456', $array['tmpName']);
        $this->assertSame('photos/pic.jpg', $array['relativePath']);
        $this->assertNull($array['lastError']);
        $this->assertSame([], $array['errors']);
    }

    public function testToJson(): void
    {
        $result = new FileUploadResult(
            isSuccess: false,
            stage: FileUploadResult::STAGE_UPLOADED,
            errors: ['Ошибка']
        );

        $json = $result->toJson();
        $decoded = json_decode($json, true);

        $this->assertIsString($json);
        $this->assertFalse($decoded['isSuccess']);
        $this->assertSame('uploaded', $decoded['stage']);
        $this->assertSame(['Ошибка'], $decoded['errors']);
    }

    public function testToJsonPretty(): void
    {
        $result = new FileUploadResult(isSuccess: true);
        $json = $result->toJson(true);

        $this->assertStringContainsString("\n", $json);
        $this->assertStringContainsString('    ', $json);
    }

    public function testToString(): void
    {
        $result = new FileUploadResult(isSuccess: true, stage: 'processed');
        $string = (string) $result;

        $this->assertJson($string);
        $decoded = json_decode($string, true);
        $this->assertTrue($decoded['isSuccess']);
    }
}
