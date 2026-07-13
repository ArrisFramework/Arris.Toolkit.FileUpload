<?php

namespace Arris\Toolkit\Media;

class ImageConvertor
{
    private string $sourcePath;
    private string $targetMime = 'image/jpeg';
    private int $quality = 90;

    private static array $supportedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    private static array $sourceMimeLoaders = [
        'image/jpeg',
        'image/jpg'     => 'imagecreatefromjpeg',
        'image/png'     => 'imagecreatefrompng',
        'image/gif'     => 'imagecreatefromgif',
        'image/webp'    => 'imagecreatefromwebp',
        'image/x-ms-bmp'=> 'imagecreatefrombmp',
    ];

    public function __construct(string $sourcePath)
    {
        $this->sourcePath = $sourcePath;
    }

    public function setTargetFormat(string $mimeType): self
    {
        $this->targetMime = $mimeType;
        return $this;
    }

    /**
     * @param int $quality 0-100 (для PNG преобразуется в compression level 0-9)
     */
    public function setQuality(int $quality): self
    {
        $this->quality = max(0, min(100, $quality));
        return $this;
    }

    /**
     * Выполняет конвертацию.
     *
     * @param string $targetPath Путь к выходному файлу
     * @return bool true при успехе, false при ошибке
     */
    public function convert(string $targetPath): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $sourceImage = $this->loadSource();
        if ($sourceImage === false) {
            return false;
        }

        $this->prepareAlpha($sourceImage);

        $result = $this->saveTarget($sourceImage, $targetPath);

        imagedestroy($sourceImage);
        return $result;
    }

    private function loadSource(): \GdImage|false
    {
        if (!is_file($this->sourcePath)) {
            return false;
        }

        $sourceMime = mime_content_type($this->sourcePath);

        return match ($sourceMime) {
            'image/jpeg',
            'image/jpg'     => imagecreatefromjpeg($this->sourcePath),
            'image/png'     => imagecreatefrompng($this->sourcePath),
            'image/gif'     => imagecreatefromgif($this->sourcePath),
            'image/webp'    => imagecreatefromwebp($this->sourcePath),
            'image/x-ms-bmp'=> imagecreatefrombmp($this->sourcePath),
            default         => false,
        };
    }

    /**
     * Для PNG/GIF: сохраняем альфа-канал при выводе.
     */
    private function prepareAlpha(\GdImage $image): void
    {
        if (in_array($this->targetMime, ['image/png', 'image/gif'], true)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }
    }

    private function saveTarget(\GdImage $image, string $targetPath): bool
    {
        return match ($this->targetMime) {
            'image/jpeg',
            'image/jpg'  => imagejpeg($image, $targetPath, $this->quality),
            'image/png'  => imagepng($image, $targetPath, $this->pngCompression()),
            'image/gif'  => imagegif($image, $targetPath),
            'image/webp' => imagewebp($image, $targetPath, $this->quality),
            default      => false,
        };
    }

    /**
     * Конвертирует quality (0-100) в уровень сжатия PNG (0-9).
     * quality 100 → compression 0 (максимум качества)
     * quality 0   → compression 9 (максимум сжатия)
     */
    private function pngCompression(): int
    {
        return max(0, min(9, round(9 * (100 - $this->quality) / 100)));
    }
}
