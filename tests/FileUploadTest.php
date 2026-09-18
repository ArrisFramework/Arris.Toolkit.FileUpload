<?php

namespace Arris\Toolkit\Tests;

use Arris\Toolkit\FileUpload;
use Arris\Toolkit\FileUpload\ErrorCode;
use Arris\Toolkit\FileUpload\ErrorMessages;
use Arris\Toolkit\FileUploadException;
use Arris\Toolkit\FileUploadResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FileUploadTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset static state before each test via reflection
        $reflection = new \ReflectionClass(FileUpload::class);

        $configProp = $reflection->getProperty('defaultConfig');
        $configProp->setAccessible(true);
        $configProp->setValue(null, []);
    }

    public function testFromFileCreatesNewInstance(): void
    {
        $file = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/phpXXXXXX',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
        ];

        $a = FileUpload::fromFile($file);
        $b = FileUpload::fromFile($file);

        $this->assertNotSame($a, $b);
        $this->assertSame($file['name'], $a->getFile()['name']);
    }

    public function testFromFileSyncMultipleFiles(): void
    {
        $files = [
            'name' => ['0' => 'a.jpg', '1' => 'b.png'],
            'type' => ['0' => 'image/jpeg', '1' => 'image/png'],
            'tmp_name' => ['0' => '/tmp/phpA', '1' => '/tmp/phpB'],
            'error' => ['0' => UPLOAD_ERR_OK, '1' => UPLOAD_ERR_OK],
            'size' => ['0' => 1000, '1' => 2000],
        ];

        $uploadA = FileUpload::fromFile($files, 0);
        $uploadB = FileUpload::fromFile($files, 1);

        $this->assertSame('a.jpg', $uploadA->getFile()['name']);
        $this->assertSame('b.png', $uploadB->getFile()['name']);
    }

    public function testSetDefaultConfig(): void
    {
        FileUpload::setDefaultConfig([
            'targetPath' => '/var/www/uploads',
            'allowedMimeTypes' => ['image/jpeg'],
            'maxFileSize' => 5 * 1024 * 1024,
            'throwExceptions' => true,
        ]);

        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ]);

        $reflection = new \ReflectionClass($upload);
        $prop = $reflection->getProperty('throwExceptions');
        $prop->setAccessible(true);

        $this->assertTrue($prop->getValue($upload));
    }

    public function testSetDefaultConfigInvalidOption(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option: foobar');

        FileUpload::setDefaultConfig([
            'foobar' => 'value',
        ]);
    }

    public function testApplyOption(): void
    {
        FileUpload::applyOption('targetPath', '/var/www/photos');
        FileUpload::applyOption('maxFileSize', 1024);
        FileUpload::applyOption('throwExceptions', true);

        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ]);

        $reflection = new \ReflectionClass($upload);
        $prop = $reflection->getProperty('throwExceptions');
        $prop->setAccessible(true);

        $this->assertTrue($prop->getValue($upload));
    }

    public function testApplyOptionInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FileUpload::applyOption('nonexistent', 'value');
    }

    public function testFluentInterface(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $result = $upload
            ->setTargetPath('/uploads')
            ->allowMimeTypes(['image/jpeg'])
            ->setMaxFileSize(1024)
            ->setFilenameGenerator(fn() => 'generated.jpg')
            ->throwExceptions(false);

        $this->assertSame($upload, $result);
    }

    public function testSetTargetPathTrailingSlash(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $upload->setTargetPath('/var/www/uploads');

        $reflection = new \ReflectionClass($upload);
        $prop = $reflection->getProperty('targetPath');
        $prop->setAccessible(true);

        $this->assertSame('/var/www/uploads/', $prop->getValue($upload));
    }

    public function testSetMaxFileSizeZero(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $upload->setMaxFileSize(0);

        $reflection = new \ReflectionClass($upload);
        $prop = $reflection->getProperty('maxFileSize');
        $prop->setAccessible(true);

        $this->assertSame(PHP_INT_MAX, $prop->getValue($upload));
    }

    public function testSetMinFileSizeDefaultNull(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $reflection = new \ReflectionClass($upload);
        $prop = $reflection->getProperty('minFileSize');
        $prop->setAccessible(true);

        $this->assertNull($prop->getValue($upload));
    }

    public function testSetMinFileSizeFluent(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $result = $upload->setMinFileSize(1024);

        $this->assertSame($upload, $result);

        $reflection = new \ReflectionClass($upload);
        $prop = $reflection->getProperty('minFileSize');
        $prop->setAccessible(true);

        $this->assertSame(1024, $prop->getValue($upload));
    }

    public function testApplyOptionMinFileSize(): void
    {
        FileUpload::applyOption('minFileSize', 2048);

        $reflection = new \ReflectionClass(FileUpload::class);
        $prop = $reflection->getProperty('defaultConfig');
        $prop->setAccessible(true);

        $this->assertSame(2048, $prop->getValue(null)['minFileSize']);
    }

    public function testUploadedReturnsFileUploadResult(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ]);

        $result = $upload->uploaded();

        $this->assertInstanceOf(\Arris\Toolkit\FileUploadResult::class, $result);
        $this->assertFalse($result->isSuccess);
        $this->assertSame('uploaded', $result->stage);
    }

    public function testUploadedEmptyFile(): void
    {
        $upload = FileUpload::fromFile([]);

        $result = $upload->uploaded();

        $this->assertFalse($result->isSuccess);
        $this->assertSame('uploaded', $result->stage);
        $this->assertContains('Файл не задан', $result->errors);
    }

    public function testUploadedNoTmpName(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/nonexistent',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $result = $upload->uploaded();

        $this->assertFalse($result->isSuccess);
        $this->assertContains('Файл не был загружен', $result->errors);
    }

    public function testUploadedUploadError(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'big.zip',
            'type' => 'application/zip',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_INI_SIZE,
            'size' => 0,
        ]);

        $result = $upload->uploaded();

        $this->assertFalse($result->isSuccess);
        $this->assertSame('uploaded', $result->stage);
        $this->assertNotEmpty($result->errors);
    }

    public function testIsUploadedAlias(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ]);

        $a = $upload->uploaded();
        $b = $upload->is_uploaded();

        $this->assertSame($a->isSuccess, $b->isSuccess);
        $this->assertSame($a->errors, $b->errors);
    }

    public function testProcessWithoutTargetPath(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $result = $upload->process();

        $this->assertFalse($result->isSuccess);
        $this->assertSame('processed', $result->stage);
        $this->assertNotEmpty($result->errors);
    }

    public function testGetErrors(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ]);

        $upload->process();

        $this->assertIsArray($upload->getErrors());
        $this->assertNotEmpty($upload->getErrors());
    }

    public function testGetFile(): void
    {
        $file = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/phpXXXXXX',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
        ];

        $upload = FileUpload::fromFile($file, 0);

        $this->assertSame('test.jpg', $upload->getFile()['name']);
        $this->assertSame('image/jpeg', $upload->getFile()['type']);
    }

    public function testGetFileIndex(): void
    {
        $upload = FileUpload::fromFile([
            'name' => ['0' => 'a.jpg', '1' => 'b.png'],
            'type' => ['0' => 'image/jpeg', '1' => 'image/png'],
            'tmp_name' => ['0' => '/tmp/phpA', '1' => '/tmp/phpB'],
            'error' => ['0' => UPLOAD_ERR_OK, '1' => UPLOAD_ERR_OK],
            'size' => ['0' => 1000, '1' => 2000],
        ], 1);

        $this->assertSame(1, $upload->getFileIndex());
    }

    public function testWithFile(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'original.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/phpA',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $newUpload = $upload->withFile([
            'name' => 'replaced.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/phpB',
            'error' => UPLOAD_ERR_OK,
            'size' => 200,
        ]);

        $this->assertSame('replaced.png', $newUpload->getFile()['name']);
        $this->assertSame('original.jpg', $upload->getFile()['name']);
    }

    public function testThrowExceptionsTrue(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $upload->setTargetPath('/nonexistent/path')->throwExceptions(true);

        $this->expectException(FileUploadException::class);
        $upload->process();
    }

    public function testValidatedFlagDefaultFalse(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $reflection = new \ReflectionClass($upload);
        $prop = $reflection->getProperty('validated');
        $prop->setAccessible(true);

        $this->assertFalse($prop->getValue($upload));
    }

    public function testWithFileResetsValidatedFlag(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $reflection = new \ReflectionClass($upload);
        $prop = $reflection->getProperty('validated');
        $prop->setAccessible(true);

        // Simulate that uploaded() validated successfully
        $prop->setValue($upload, true);
        $this->assertTrue($prop->getValue($upload));

        // withFile() should reset
        $newUpload = $upload->withFile([
            'name' => 'other.png',
            'type' => 'image/png',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 200,
        ]);

        $this->assertFalse($prop->getValue($newUpload));
    }

    public function testProcessSkipsValidationWhenAlreadyValidated(): void
    {
        // Создаём временный файл, чтобы mime_content_type() не падал
        $tmpFile = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($tmpFile, 'fake image data');

        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $upload->setTargetPath('/tmp/upload_test_dir_' . getmypid());

        $reflection = new \ReflectionClass($upload);
        $prop = $reflection->getProperty('validated');
        $prop->setAccessible(true);

        // Simulate that uploaded() already validated
        $prop->setValue($upload, true);

        // process() should skip validate() and go straight to file operations
        $result = $upload->process();

        // move_uploaded_file() откажет (файл не загружен через HTTP),
        // но ошибки валидации быть не должно
        $this->assertFalse($result->isSuccess);
        $this->assertNotEmpty($result->errors);

        foreach ($result->errors as $error) {
            $this->assertStringNotContainsString('Файл не был загружен', $error);
            $this->assertStringNotContainsString('Недопустимый тип', $error);
            $this->assertStringNotContainsString('Файл слишком большой', $error);
        }

        @unlink($tmpFile);
        @rmdir('/tmp/upload_test_dir_' . getmypid());
    }

    public function testProcessRunsValidationWhenNotValidated(): void
    {
        // No allowedMimeTypes set → validate() should pass MIME check
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $upload->setTargetPath('/tmp');
        $result = $upload->process();

        $this->assertFalse($result->isSuccess);
        // Error should be about is_uploaded_file (basic check in validate), not about file move
        $this->assertContains('Файл не был загружен', $result->errors);
    }

    public function testUploadedReturnsRealMimeType(): void
    {
        // uploaded() should use mime_content_type() instead of browser-provided type
        // This can only be tested when is_uploaded_file() returns true (integration test)
        // Here we verify the method doesn't crash when basic checks fail
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ]);

        $result = $upload->uploaded();

        $this->assertFalse($result->isSuccess);
        $this->assertSame('uploaded', $result->stage);
    }

    public function testGetErrorStackReturnsCodesAfterProcess(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $upload->process();

        $stack = $upload->getErrorStack();

        $this->assertIsArray($stack);
        $this->assertNotEmpty($stack);
        $this->assertArrayHasKey('code', $stack[0]);
        $this->assertInstanceOf(ErrorCode::class, $stack[0]['code']);
    }

    public function testGetErrorsResolvesViaTranslator(): void
    {
        ErrorMessages::setMessage(ErrorCode::FILE_NOT_SET, 'Нет файла!');

        $upload = FileUpload::fromFile([]);
        $upload->uploaded(); // заполняет errorStack

        $errors = $upload->getErrors();

        $this->assertContains('Нет файла!', $errors);

        ErrorMessages::reset();
    }

    public function testErrorStackEmptyByDefault(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $this->assertEmpty($upload->getErrorStack());
    }

    public function testFilenameGeneratorReceivesSourceResult(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'upload_test_');
        $image = imagecreatetruecolor(2, 2);
        imagejpeg($image, $tmpFile, 95);
        imagedestroy($image);

        $targetDir = '/tmp/upload_test_dir_' . getmypid();
        @mkdir($targetDir, 0755, true);

        $capturedSource = null;
        $generator = function (FileUploadResult $source) use (&$capturedSource): string {
            $capturedSource = $source;
            return 'generated_from_source.jpg';
        };

        $upload = FileUpload::fromFile([
            'name' => 'photo.png',
            'type' => 'image/png',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ]);

        $upload->setTargetPath($targetDir)->setFilenameGenerator($generator);

        // Simulate already-validated state to exercise ensureUploadedResult()
        $reflection = new \ReflectionClass($upload);
        $validatedProp = $reflection->getProperty('validated');
        $validatedProp->setAccessible(true);
        $validatedProp->setValue($upload, true);

        $result = $upload->process();

        // move_uploaded_file() will reject (non-HTTP upload) → FILE_MOVE_FAILED
        $this->assertFalse($result->isSuccess);

        // But the generator was called with a valid source descriptor
        $this->assertInstanceOf(FileUploadResult::class, $capturedSource);
        $this->assertTrue($capturedSource->isSuccess);
        $this->assertSame('uploaded', $capturedSource->stage);
        $this->assertSame('photo.png', $capturedSource->originalName);
        $this->assertSame('image/jpeg', $capturedSource->mimeType);
        $this->assertSame($tmpFile, $capturedSource->tmpName);
        $this->assertSame('photo.png', $capturedSource->relativePath);
        $this->assertIsInt($capturedSource->width);
        $this->assertIsInt($capturedSource->height);

        // No validation errors — only file-move error
        foreach ($result->errors as $error) {
            $this->assertStringNotContainsString('Файл не был загружен', $error);
            $this->assertStringNotContainsString('Недопустимый тип', $error);
            $this->assertStringNotContainsString('Файл слишком большой', $error);
        }

        @unlink($tmpFile);
        @unlink($targetDir);
    }

    public function testWithFileResetsUploadCaches(): void
    {
        $upload = FileUpload::fromFile([
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/phpXXXXXX',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);

        $reflection = new \ReflectionClass($upload);
        $uploadedResultProp = $reflection->getProperty('uploadedResult');
        $uploadedResultProp->setAccessible(true);
        $detectedMimeProp = $reflection->getProperty('detectedMimeType');
        $detectedMimeProp->setAccessible(true);

        // Populate caches via reflection (simulating a prior uploaded() call)
        $uploadedResultProp->setValue($upload, new FileUploadResult(isSuccess: true, stage: FileUploadResult::STAGE_UPLOADED));
        $detectedMimeProp->setValue($upload, 'image/jpeg');

        $newUpload = $upload->withFile([
            'name' => 'new.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/phpYYYYYY',
            'error' => UPLOAD_ERR_OK,
            'size' => 200,
        ]);

        $this->assertNull($uploadedResultProp->getValue($newUpload));
        $this->assertNull($detectedMimeProp->getValue($newUpload));
        // Original instance untouched
        $this->assertNotNull($uploadedResultProp->getValue($upload));
        $this->assertNotNull($detectedMimeProp->getValue($upload));
    }
}
