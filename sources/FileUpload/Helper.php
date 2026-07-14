<?php

namespace Arris\Toolkit\FileUpload;

class Helper
{
    /**
     * Конвертирует строковое значение размера (например "64M", "1G", "1024K") в количество байт.
     *
     * @param string|int|float $val
     * @return int
     */
    public static function returnBytes(string|int|float $val): int
    {
        $val = trim((string)$val);

        if ($val === '') {
            return 0;
        }

        if (preg_match('/^\s*([0-9]+(?:\.[0-9]+)?)\s*([kmg]b?)?\s*$/i', $val, $matches)) {
            $numericValue = (float)$matches[1];
            $suffix = strtolower($matches[2] ?? '');
            $baseSuffix = $suffix[0] ?? '';

            return match ($baseSuffix) {
                'g' => (int)($numericValue * (1024 ** 3)),
                'm' => (int)($numericValue * (1024 ** 2)),
                'k' => (int)($numericValue * 1024),
                default => (int)$numericValue,
            };
        }

        return 0;
    }

    /**
     * Возвращает значение php.ini директивы, сконвертированное в байты.
     *
     * @param string $key Имя директивы (например 'upload_max_filesize', 'post_max_size')
     * @return int
     */
    public static function getIniValue(string $key): int
    {
        return self::returnBytes(ini_get($key));
    }

    /**
     * Вычисляет реальный максимально допустимый размер загружаемого файла.
     *
     * Учитывает три ограничения:
     * - post_max_size (лимит POST-запроса)
     * - upload_max_filesize (лимит PHP-скрипта)
     * - configMaxSize (прикладной лимит приложения)
     *
     * Возвращает ассоциативный массив:
     * - POST_MAX_SIZE    : int  — лимит post_max_size в байтах
     * - UPLOAD_MAX_SIZE  : int  — лимит upload_max_filesize в байтах
     * - CONFIG_MAX_SIZE  : int  — прикладной лимит в байтах
     * - REAL_MAX_SIZE    : int  — реальный максимум (минимум из трёх)
     * - IS_WRONG_SIZE    : bool — конфигурация некорректна (превышает физические лимиты)
     *
     * @param string $applicationMaxSize Строка размера из конфигурации приложения (например '64M')
     *
     * @return array{POST_MAX_SIZE: int, UPLOAD_MAX_SIZE: int, CONFIG_MAX_SIZE: int, REAL_MAX_SIZE: int, IS_WRONG_SIZE: bool}
     */
    public static function getUploadLimits(string $applicationMaxSize = '64M'): array
    {
        $limits = [
            'POST_MAX_SIZE'   => self::getIniValue('post_max_size'),
            'UPLOAD_MAX_SIZE' => self::getIniValue('upload_max_filesize'),
            'CONFIG_MAX_SIZE' => self::returnBytes($applicationMaxSize),
        ];

        $limits['REAL_MAX_SIZE'] = min(
            $limits['POST_MAX_SIZE'],
            $limits['UPLOAD_MAX_SIZE'],
            $limits['CONFIG_MAX_SIZE']
        );

        $limits['IS_WRONG_SIZE']
            = ($limits['CONFIG_MAX_SIZE'] > min($limits['POST_MAX_SIZE'], $limits['UPLOAD_MAX_SIZE']))
            || ($limits['POST_MAX_SIZE'] < $limits['UPLOAD_MAX_SIZE']);

        return $limits;
    }
}
