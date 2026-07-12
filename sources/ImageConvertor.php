<?php

namespace Arris\Toolkit;

class ImageConvertor
{
    public static function convert(string $sourcePath, string $targetPath, string $targetMime, int $quality = 90): bool
    {
        return self::convertWithGD($sourcePath, $targetPath, $targetMime, $quality);
    }

    private static function convertWithGD(string $sourcePath, string $targetPath, string $targetMime, $quality): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $sourceMime = mime_content_type($sourcePath);
        $sourceImage = match ($sourceMime) {
            'image/jpeg',
            'image/jpg'     =>  imagecreatefromjpeg($sourcePath),
            'image/png'     =>  imagecreatefrompng($sourcePath),
            'image/gif'     =>  imagecreatefromgif($sourcePath),
            'image/webp'    =>  imagecreatefromwebp($sourcePath),
            'image/x-ms-bmp'=>  imagecreatefrombmp($sourcePath),
            default         => false
        };

        if (!$sourceImage) {
            return false;
        }

        $result = match ($targetMime) {
            'image/jpeg',
            'image/jpg'     =>  imagejpeg($sourceImage, $targetPath, max(0, min(100, $quality))),
            'image/png'     =>  imagepng($sourceImage, $targetPath, max(0, min(10, $quality))),
            'image/gif'     =>  imagegif($sourceImage, $targetPath),
            'image/webp'    =>  imagewebp($sourceImage, $targetPath, max(0, min(100, $quality))),
            default         =>  false
        };

        imagedestroy($sourceImage);
        return $result;
    }

}
