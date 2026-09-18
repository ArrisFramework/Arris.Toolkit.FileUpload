<?php

namespace Arris\Toolkit\Tests;

use Arris\Toolkit\FileUpload\ErrorCode;
use Arris\Toolkit\FileUpload\ErrorMessages;
use PHPUnit\Framework\TestCase;

class FileUploadErrorMessagesTest extends TestCase
{
    protected function setUp(): void
    {
        ErrorMessages::reset();
    }

    public function testResolveReturnsDefaultMessage(): void
    {
        $result = ErrorMessages::resolve(ErrorCode::FILE_NOT_SET);

        $this->assertSame('Файл не задан', $result);
    }

    public function testResolveWithParams(): void
    {
        $result = ErrorMessages::resolve(
            ErrorCode::INVALID_MIME_TYPE,
            ['mime_type' => 'image/jpeg']
        );

        $this->assertSame('Недопустимый тип файла: image/jpeg', $result);
    }

    public function testResolveWithExceptionMessage(): void
    {
        $result = ErrorMessages::resolve(
            ErrorCode::EXCEPTION,
            ['message' => ' division by zero']
        );

        $this->assertSame('Исключение:  division by zero', $result);
    }

    public function testSetMessageOverridesDefault(): void
    {
        ErrorMessages::setMessage(
            ErrorCode::FILE_TOO_LARGE,
            'Максимальный размер — 10 МБ'
        );

        $result = ErrorMessages::resolve(ErrorCode::FILE_TOO_LARGE);

        $this->assertSame('Максимальный размер — 10 МБ', $result);
    }

    public function testSetMessagesBulkOverride(): void
    {
        ErrorMessages::setMessages([
            'file_too_small'  => 'Слишком мало!',
            'file_too_large'  => 'Слишком много!',
        ]);

        $this->assertSame('Слишком мало!', ErrorMessages::resolve(ErrorCode::FILE_TOO_SMALL));
        $this->assertSame('Слишком много!', ErrorMessages::resolve(ErrorCode::FILE_TOO_LARGE));
        // Остальные не затронуты
        $this->assertSame('Файл не задан', ErrorMessages::resolve(ErrorCode::FILE_NOT_SET));
    }

    public function testResetRestoresDefaults(): void
    {
        ErrorMessages::setMessage(ErrorCode::FILE_NOT_SET, 'custom');
        $this->assertSame('custom', ErrorMessages::resolve(ErrorCode::FILE_NOT_SET));

        ErrorMessages::reset();

        $this->assertSame('Файл не задан', ErrorMessages::resolve(ErrorCode::FILE_NOT_SET));
    }

    public function testResolveUnknownCodeFallsBackToValue(): void
    {
        $result = ErrorMessages::resolve(ErrorCode::NOT_UPLOADED);

        $this->assertSame('Файл не был загружен', $result);
    }

    public function testAllEnumCasesHaveMessages(): void
    {
        foreach (ErrorCode::cases() as $code) {
            $message = ErrorMessages::resolve($code);
            $this->assertNotEmpty($message, "Empty message for code: {$code->value}");
            $this->assertNotSame($code->value, $message, "Unresolved fallback for code: {$code->value}");
        }
    }

    // ─── i18n tests ─────────────────────────────────────────────────────

    public function testDefaultLocaleIsRussian(): void
    {
        $this->assertSame('ru', ErrorMessages::getLocale());
    }

    public function testSetLocale(): void
    {
        ErrorMessages::setLocale('en');

        $this->assertSame('en', ErrorMessages::getLocale());

        ErrorMessages::reset();
    }

    public function testResolveInEnglish(): void
    {
        ErrorMessages::setLocale('en');

        $result = ErrorMessages::resolve(ErrorCode::FILE_NOT_SET);

        $this->assertSame('File not set', $result);

        ErrorMessages::reset();
    }

    public function testResolveWithParamsInEnglish(): void
    {
        ErrorMessages::setLocale('en');

        $result = ErrorMessages::resolve(
            ErrorCode::INVALID_MIME_TYPE,
            ['mime_type' => 'image/jpeg']
        );

        $this->assertSame('Invalid file type: image/jpeg', $result);

        ErrorMessages::reset();
    }

    public function testAllEnumCasesHaveEnglishMessages(): void
    {
        ErrorMessages::setLocale('en');

        foreach (ErrorCode::cases() as $code) {
            $message = ErrorMessages::resolve($code);
            $this->assertNotEmpty($message, "Empty English message for code: {$code->value}");
            $this->assertNotSame($code->value, $message, "Unresolved English fallback for code: {$code->value}");
        }

        ErrorMessages::reset();
    }

    public function testOverrideWorksWithLocale(): void
    {
        ErrorMessages::setLocale('en');

        ErrorMessages::setMessage(
            ErrorCode::FILE_TOO_LARGE,
            'Max size exceeded'
        );

        $this->assertSame('Max size exceeded', ErrorMessages::resolve(ErrorCode::FILE_TOO_LARGE));

        ErrorMessages::reset();
    }

    public function testResetRestoresLocaleToRussian(): void
    {
        ErrorMessages::setLocale('en');
        $this->assertSame('en', ErrorMessages::getLocale());

        ErrorMessages::reset();

        $this->assertSame('ru', ErrorMessages::getLocale());
    }
}
