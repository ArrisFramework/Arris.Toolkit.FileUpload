<?php

namespace Arris\Toolkit;

/**
 * Транслятор кодов ошибок FileUploadErrorCode в человекочитаемые строки.
 *
 * Поддерживает:
 *  - замену отдельных сообщений: setMessage(FileUploadErrorCode::FILE_TOO_LARGE, 'Максимум 10 МБ')
 *  - пакетную замену (для i18n): setMessages([ ... ])
 *  - расширение через наследование: class MyTranslator extends FileUploadErrorMessages
 *
 * Параметры в сообщениях: {mime_type}, {message} — подставляются из массива $params.
 */
class FileUploadErrorMessages
{
    private static array $messages = [];

    private static bool $initialized = false;

    private static function defaults(): array
    {
        return [
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
        ];
    }

    private static function init(): void
    {
        if (!self::$initialized) {
            self::$messages = self::defaults();
            self::$initialized = true;
        }
    }

    /**
     * Резолвит код ошибки в строку, подставляя параметры.
     *
     * @param FileUploadErrorCode $code
     * @param array               $params  ключ => значение для подстановки в {placeholder}
     * @return string
     */
    public static function resolve(FileUploadErrorCode $code, array $params = []): string
    {
        self::init();

        $message = self::$messages[$code->value] ?? $code->value;

        foreach ($params as $key => $value) {
            $message = str_replace('{' . $key . '}', (string)$value, $message);
        }

        return $message;
    }

    /**
     * Заменяет сообщение для одного кода.
     */
    public static function setMessage(FileUploadErrorCode $code, string $message): void
    {
        self::init();
        self::$messages[$code->value] = $message;
    }

    /**
     * Пакетная замена / дополнение сообщений.
     * Удобно для i18n: передаёте массив ['file_too_large' => 'Максимум 10 МБ', ...]
     *
     * @param array<string, string> $messages ключ — FileUploadErrorCode->value, значение — шаблон
     */
    public static function setMessages(array $messages): void
    {
        self::init();
        self::$messages = array_merge(self::$messages, $messages);
    }

    /**
     * Сбрасывает все сообщения к дефолту (полезно в тестах).
     */
    public static function reset(): void
    {
        self::$messages = self::defaults();
        self::$initialized = true;
    }
}
