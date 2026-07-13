<?php

namespace Arris\Toolkit\FileUpload;

/**
 * Результат анализа медиафайла через ffprobe.
 *
 * @property-read float|null $duration       Длительность в секундах
 * @property-read int|null    $size           Размер файла в байтах
 * @property-read int|null    $bitrate        Общий битрейт (bps)
 * @property-read string|null $format         Имя контейнера (mov,mp4,...)
 * @property-read int|null    $width          Ширина видео (px)
 * @property-read int|null    $height         Высота видео (px)
 * @property-read string|null $videoCodec     Кодек видео (h264, hevc, ...)
 * @property-read int|null    $videoBitrate   Битрейт видео (bps)
 * @property-read float|null  $fps            Кадров в секунду
 * @property-read string|null $pixelFormat    Пиксельный формат (yuv420p, ...)
 * @property-read string|null $audioCodec     Кодек аудио (aac, mp3, ...)
 * @property-read int|null    $audioBitrate   Битрейт аудио (bps)
 * @property-read int|null    $sampleRate     Частота дискретизации (Hz)
 * @property-read int|null    $channels       Количество каналов
 * @property-read string|null $channelLayout  Раскладка каналов (stereo, 5.1, ...)
 */
class MediaProbeResult
{
    public function __construct(
        public readonly ?float $duration = null,
        public readonly ?int $size = null,
        public readonly ?int $bitrate = null,
        public readonly ?string $format = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $videoCodec = null,
        public readonly ?int $videoBitrate = null,
        public readonly ?float $fps = null,
        public readonly ?string $pixelFormat = null,
        public readonly ?string $audioCodec = null,
        public readonly ?int $audioBitrate = null,
        public readonly ?int $sampleRate = null,
        public readonly ?int $channels = null,
        public readonly ?string $channelLayout = null,
    ) {}
}
