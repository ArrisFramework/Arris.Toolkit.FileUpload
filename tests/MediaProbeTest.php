<?php

namespace Arris\Toolkit\Tests;

use Arris\Toolkit\FileUpload\MediaProbe;
use Arris\Toolkit\FileUpload\MediaProbeResult;
use PHPUnit\Framework\TestCase;

class MediaProbeTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/media_probe_test_' . getmypid();
        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') as $file) {
            @unlink($file);
        }
        @rmdir($this->tmpDir);
    }

    private function skipIfNoFfprobe(): void
    {
        exec('which ffprobe 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0) {
            $this->markTestSkipped('ffprobe not found');
        }
    }

    private function createTestVideo(string $filename = 'test.mp4'): string
    {
        $path = $this->tmpDir . '/' . $filename;
        exec(sprintf(
            'ffmpeg -y -f lavfi -i "color=c=black:s=640x480:d=1" -f lavfi -i "anullsrc=r=44100:cl=stereo" -c:v libx264 -c:a aac -shortest %s 2>/dev/null',
            escapeshellarg($path)
        ));
        return $path;
    }

    public function testProbeNonExistentFile(): void
    {
        $result = MediaProbe::probe('/nonexistent/file.mp4');
        $this->assertFalse($result);
    }

    public function testProbeNonMediaFile(): void
    {
        $file = $this->tmpDir . '/readme.txt';
        file_put_contents($file, 'not a video file');

        $result = MediaProbe::probe($file);
        $this->assertFalse($result);
    }

    public function testProbeReturnsObject(): void
    {
        $this->skipIfNoFfprobe();

        $file = $this->createTestVideo();
        if (!is_file($file)) {
            $this->markTestSkipped('Could not create test video');
        }

        $result = MediaProbe::probe($file);

        $this->assertInstanceOf(MediaProbeResult::class, $result);
        $this->assertNotNull($result->duration);
        $this->assertNotNull($result->width);
        $this->assertNotNull($result->height);
        $this->assertNotNull($result->videoCodec);
        $this->assertNotNull($result->fps);
    }

    public function testProbeVideoDimensions(): void
    {
        $this->skipIfNoFfprobe();

        $file = $this->createTestVideo();
        if (!is_file($file)) {
            $this->markTestSkipped('Could not create test video');
        }

        $result = MediaProbe::probe($file);

        $this->assertSame(640, $result->width);
        $this->assertSame(480, $result->height);
    }

    public function testProbeVideoCodec(): void
    {
        $this->skipIfNoFfprobe();

        $file = $this->createTestVideo();
        if (!is_file($file)) {
            $this->markTestSkipped('Could not create test video');
        }

        $result = MediaProbe::probe($file);

        $this->assertSame('h264', $result->videoCodec);
        $this->assertSame('aac', $result->audioCodec);
    }

    public function testProbeDuration(): void
    {
        $this->skipIfNoFfprobe();

        $file = $this->createTestVideo();
        if (!is_file($file)) {
            $this->markTestSkipped('Could not create test video');
        }

        $result = MediaProbe::probe($file);

        $this->assertIsFloat($result->duration);
        $this->assertGreaterThan(0, $result->duration);
        $this->assertLessThanOrEqual(2, $result->duration);
    }

    public function testProbeHasAllFields(): void
    {
        $this->skipIfNoFfprobe();

        $file = $this->createTestVideo();
        if (!is_file($file)) {
            $this->markTestSkipped('Could not create test video');
        }

        $result = MediaProbe::probe($file);

        $this->assertNotNull($result->duration);
        $this->assertNotNull($result->size);
        $this->assertNotNull($result->format);
        $this->assertNotNull($result->width);
        $this->assertNotNull($result->height);
        $this->assertNotNull($result->videoCodec);
        $this->assertNotNull($result->fps);
        $this->assertNotNull($result->audioCodec);
        $this->assertNotNull($result->sampleRate);
        $this->assertNotNull($result->channels);
    }
}
