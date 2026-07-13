<?php

namespace Arris\Toolkit\FileUpload;

/**
 * Утилита для получения метаданных медиафайлов через ffprobe.
 *
 * Требует установленный ffprobe (part of ffmpeg).
 * Не выбрасывает исключений — возвращает false при ошибке.
 */
class MediaProbe
{
    /**
     * Анализирует медиафайл и возвращает его метаданные.
     *
     * @param string $filepath Путь к файлу
     *
     * @return MediaProbeResult|false Объект с метаданными или false при ошибке
     * @throws \JsonException
     */
    public static function probe(string $filepath): MediaProbeResult|false
    {
        if (!is_file($filepath)) {
            return false;
        }

        $cmd = sprintf(
            'ffprobe -v quiet -print_format json -show_format -show_streams %s 2>&1',
            escapeshellarg($filepath)
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || empty($output)) {
            return false;
        }

        $json = implode("\n", $output);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            return false;
        }

        return self::extract($data);
    }

    /**
     * Извлекает ключевые поля из сырых данных ffprobe.
     */
    private static function extract(array $data): MediaProbeResult
    {
        $format = $data['format'] ?? [];
        $streams = $data['streams'] ?? [];

        // Ищем первый видео-поток
        $video = null;
        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                $video = $stream;
                break;
            }
        }

        // Ищем первый аудио-поток
        $audio = null;
        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? '') === 'audio') {
                $audio = $stream;
                break;
            }
        }

        return new MediaProbeResult(
            duration:       self::toFloat($format['duration'] ?? null),
            size:           self::toInt($format['size'] ?? null),
            bitrate:        self::toInt($format['bit_rate'] ?? null),
            format:         $format['format_name'] ?? null,
            width:          self::toInt($video['width'] ?? null),
            height:         self::toInt($video['height'] ?? null),
            videoCodec:     $video['codec_name'] ?? null,
            videoBitrate:   self::toInt($video['bit_rate'] ?? null),
            fps:            self::parseFps($video['r_frame_rate'] ?? null),
            pixelFormat:    $video['pix_fmt'] ?? null,
            audioCodec:     $audio['codec_name'] ?? null,
            audioBitrate:   self::toInt($audio['bit_rate'] ?? null),
            sampleRate:     self::toInt($audio['sample_rate'] ?? null),
            channels:       self::toInt($audio['channels'] ?? null),
            channelLayout:  $audio['channel_layout'] ?? null,
        );
    }

    /**
     * Парсит frame rate из строки-дроби ("30/1", "30000/1001") в float.
     */
    private static function parseFps(?string $rate): ?float
    {
        if ($rate === null || $rate === '') {
            return null;
        }

        if (str_contains($rate, '/')) {
            [$num, $den] = explode('/', $rate, 2);
            $den = (float)$den;
            if ($den === 0.0) {
                return null;
            }
            return round((float)$num / $den, 2);
        }

        return (float)$rate;
    }

    private static function toInt(mixed $value): ?int
    {
        return $value !== null ? (int)$value : null;
    }

    private static function toFloat(mixed $value): ?float
    {
        return $value !== null ? (float)$value : null;
    }
}
