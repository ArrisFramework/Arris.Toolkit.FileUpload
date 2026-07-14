<?php

namespace Arris\Toolkit\Tests;

use Arris\Toolkit\FileUpload\Helper;
use PHPUnit\Framework\TestCase;

class FileUploadHelperTest extends TestCase
{
    // ─── returnBytes ──────────────────────────────────────────────────────

    public function testReturnBytesPlainNumber(): void
    {
        $this->assertSame(1024, Helper::returnBytes('1024'));
        $this->assertSame(0, Helper::returnBytes('0'));
        $this->assertSame(42, Helper::returnBytes(42));
    }

    public function testReturnBytesKilobytes(): void
    {
        $this->assertSame(1024, Helper::returnBytes('1K'));
        $this->assertSame(1024, Helper::returnBytes('1k'));
        $this->assertSame(1024, Helper::returnBytes('1kb'));
        $this->assertSame(1024, Helper::returnBytes('1KB'));
        $this->assertSame(5 * 1024, Helper::returnBytes('5k'));
    }

    public function testReturnBytesMegabytes(): void
    {
        $this->assertSame(1048576, Helper::returnBytes('1M'));
        $this->assertSame(1048576, Helper::returnBytes('1m'));
        $this->assertSame(1048576, Helper::returnBytes('1mb'));
        $this->assertSame(1048576, Helper::returnBytes('1MB'));
        $this->assertSame(64 * 1048576, Helper::returnBytes('64M'));
    }

    public function testReturnBytesGigabytes(): void
    {
        $this->assertSame(1073741824, Helper::returnBytes('1G'));
        $this->assertSame(1073741824, Helper::returnBytes('1g'));
        $this->assertSame(1073741824, Helper::returnBytes('1gb'));
        $this->assertSame(2 * 1073741824, Helper::returnBytes('2G'));
    }

    public function testReturnBytesFloatValues(): void
    {
        $this->assertSame(1536, Helper::returnBytes('1.5K'));
        $this->assertSame(1572864, Helper::returnBytes('1.5M'));
        $this->assertSame((int)(1.5 * 1073741824), Helper::returnBytes('1.5G'));
    }

    public function testReturnBytesWhitespace(): void
    {
        $this->assertSame(1048576, Helper::returnBytes('  1M  '));
        $this->assertSame(1048576, Helper::returnBytes("  1M\t"));
    }

    public function testReturnBytesEmptyAndInvalid(): void
    {
        $this->assertSame(0, Helper::returnBytes(''));
        $this->assertSame(0, Helper::returnBytes('abc'));
        $this->assertSame(0, Helper::returnBytes('10tb'));
        $this->assertSame(0, Helper::returnBytes('MB'));
    }

    // ─── getIniValue ──────────────────────────────────────────────────────

    public function testGetIniValueReturnsInt(): void
    {
        $result = Helper::getIniValue('upload_max_filesize');
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testGetIniValuePostMaxSize(): void
    {
        $result = Helper::getIniValue('post_max_size');
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    // ─── getUploadLimits ──────────────────────────────────────────────────

    public function testGetUploadLimitsReturnsExpectedKeys(): void
    {
        $limits = Helper::getUploadLimits();

        $this->assertArrayHasKey('POST_MAX_SIZE', $limits);
        $this->assertArrayHasKey('UPLOAD_MAX_SIZE', $limits);
        $this->assertArrayHasKey('CONFIG_MAX_SIZE', $limits);
        $this->assertArrayHasKey('REAL_MAX_SIZE', $limits);
        $this->assertArrayHasKey('IS_WRONG_SIZE', $limits);
    }

    public function testGetUploadLimitsTypes(): void
    {
        $limits = Helper::getUploadLimits();

        $this->assertIsInt($limits['POST_MAX_SIZE']);
        $this->assertIsInt($limits['UPLOAD_MAX_SIZE']);
        $this->assertIsInt($limits['CONFIG_MAX_SIZE']);
        $this->assertIsInt($limits['REAL_MAX_SIZE']);
        $this->assertIsBool($limits['IS_WRONG_SIZE']);
    }

    public function testGetUploadLimitsRealIsMinOfThree(): void
    {
        $limits = Helper::getUploadLimits('64M');

        $this->assertSame(
            min($limits['POST_MAX_SIZE'], $limits['UPLOAD_MAX_SIZE'], $limits['CONFIG_MAX_SIZE']),
            $limits['REAL_MAX_SIZE']
        );
    }

    public function testGetUploadLimitsCustomConfigSize(): void
    {
        $limits = Helper::getUploadLimits('32M');

        $this->assertSame(32 * 1024 * 1024, $limits['CONFIG_MAX_SIZE']);
        $this->assertLessThanOrEqual($limits['CONFIG_MAX_SIZE'], $limits['REAL_MAX_SIZE']);
    }

    public function testGetUploadLimitsSmallConfigForcesLimit(): void
    {
        $limits = Helper::getUploadLimits('1K');

        $this->assertSame(1024, $limits['CONFIG_MAX_SIZE']);
        $this->assertSame(1024, $limits['REAL_MAX_SIZE']);
    }
}
