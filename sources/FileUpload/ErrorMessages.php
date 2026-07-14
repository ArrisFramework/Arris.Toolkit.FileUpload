<?php

namespace Arris\Toolkit\FileUpload;

/**
 * Транслятор кодов ошибок ErrorCode в человекочитаемые строки.
 *
 * Поддерживает i18n:
 *  - ключ `_` — дефолтная локаль (русская)
 *  - setLocale('en') — переключение на английский
 *  - setMessage() / setMessages() — ручная кастомизация для текущей локали
 *
 * Параметры в сообщениях: {mime_type}, {message} — подставляются из массива $params.
 */
class ErrorMessages
{
    private const MESSAGES = [
        '_' => [
            // Общие
            'file_not_set'         => 'Файл не задан',
            'not_uploaded'         => 'Файл не был загружен',

            // PHP upload-ошибки
            'upload_err_ini_size'  => 'Размер файла превышает upload_max_filesize в php.ini',
            'upload_err_form_size' => 'Размер файла превышает MAX_FILE_SIZE в форме',
            'upload_err_partial'   => 'Файл был загружен только частично',
            'upload_err_no_file'   => 'Файл не был загружен',
            'upload_err_no_tmp_dir' => 'Отсутствует временная директория',
            'upload_err_cant_write' => 'Не удалось записать файл на диск',
            'upload_err_extension' => 'Загрузка файла была остановлена расширением',

            // Валидация
            'invalid_mime_type'    => 'Недопустимый тип файла: {mime_type}',
            'file_too_small'       => 'Файл слишком маленький',
            'file_too_large'       => 'Файл слишком большой',
            'validator_failed'     => '{message}',

            // Файловые операции
            'target_path_not_set'      => 'Не указан путь для сохранения файла',
            'target_dir_create_failed'  => 'Не удалось создать директорию для сохранения файла',
            'file_move_failed'          => 'Не удалось переместить загруженный файл',
            'conversion_failed'         => 'Не удалось конвертировать файл',

            // Исключение
            'exception' => 'Исключение: {message}',
        ],

        'en' => [
            // General
            'file_not_set'         => 'File not set',
            'not_uploaded'         => 'File was not uploaded',

            // PHP upload errors
            'upload_err_ini_size'  => 'File size exceeds upload_max_filesize in php.ini',
            'upload_err_form_size' => 'File size exceeds MAX_FILE_SIZE in form',
            'upload_err_partial'   => 'File was only partially uploaded',
            'upload_err_no_file'   => 'No file was uploaded',
            'upload_err_no_tmp_dir' => 'Temporary directory missing',
            'upload_err_cant_write' => 'Failed to write file to disk',
            'upload_err_extension' => 'Upload stopped by extension',

            // Validation
            'invalid_mime_type'    => 'Invalid file type: {mime_type}',
            'file_too_small'       => 'File is too small',
            'file_too_large'       => 'File is too large',
            'validator_failed'     => '{message}',

            // File operations
            'target_path_not_set'      => 'Target path not specified',
            'target_dir_create_failed'  => 'Failed to create target directory',
            'file_move_failed'          => 'Failed to move uploaded file',
            'conversion_failed'         => 'Failed to convert file',

            // Exception
            'exception' => 'Exception: {message}',
        ],
    ];

    private static string $locale = 'ru';

    /** @var array<string, string> Кастомные оверрайды для текущей локали */
    private static array $overrides = [];

    /**
     * Устанавливает текущую локаль.
     */
    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
    }

    /**
     * Возвращает текущую локаль.
     */
    public static function getLocale(): string
    {
        return self::$locale;
    }

    /**
     * Резолвит код ошибки в строку, подставляя параметры.
     *
     * Порядок поиска:
     * 1. Кастомный оверрайд (setMessage)
     * 2. Сообщение для текущей локали
     * 3. Сообщение для дефолтной локали (_)
     * 4. value кода ошибки
     */
    public static function resolve(ErrorCode $code, array $params = []): string
    {
        $key = $code->value;

        // 1. Кастомный оверрайд
        if (isset(self::$overrides[$key])) {
            $message = self::$overrides[$key];
        }
        // 2. Текущая локаль
        elseif (isset(self::MESSAGES[self::$locale][$key])) {
            $message = self::MESSAGES[self::$locale][$key];
        }
        // 3. Дефолтная локаль
        elseif (isset(self::MESSAGES['_'][$key])) {
            $message = self::MESSAGES['_'][$key];
        }
        // 4. Fallback на value
        else {
            $message = $code->value;
        }

        foreach ($params as $paramKey => $value) {
            $message = str_replace('{' . $paramKey . '}', (string)$value, $message);
        }

        return $message;
    }

    /**
     * Заменяет сообщение для одного кода (для текущей локали).
     */
    public static function setMessage(ErrorCode $code, string $message): void
    {
        self::$overrides[$code->value] = $message;
    }

    /**
     * Пакетная замена / дополнение сообщений.
     *
     * @param array<string, string> $messages ключ — ErrorCode->value, значение — шаблон
     */
    public static function setMessages(array $messages): void
    {
        self::$overrides = array_merge(self::$overrides, $messages);
    }

    /**
     * Сбрасывает все сообщения к дефолту (полезно в тестах).
     */
    public static function reset(): void
    {
        self::$locale = 'ru';
        self::$overrides = [];
    }
}
