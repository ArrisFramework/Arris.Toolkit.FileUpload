<?php

namespace Arris\Toolkit;

/**
 * @property-read bool        $isSuccess     Успешность операции
 * @property-read string|null $stage         Этап: 'uploaded' (проверка is_uploaded_file) или 'processed' (файл перемещён в storage)
 * @property-read string|null $originalName  Оригинальное имя файла
 * @property-read string|null $savedName     Имя файла в хранилище (сгенерированное на этапе process) — с расширением и точкой
 * @property-read string|null $path          Полный путь от корня ФС к каталогу с файлом (опция targetPath или метод setTargetPath())
 * @property-read string|null $fullPath      Полный путь от корня ФС к каталогу с файлом + имя файла. Пригодно для файловых операций с файлом в обход класса
 * @property-read string|null $mimeType      MIME-тип файла
 * @property-read int|null    $size          Размер файла
 * @property-read string|null $lastError     Последняя ошибка
 * @property-read array       $errors        Массив со списком ошибок
 * @property-read string|null $radix         Корень имени файла
 * @property-read string|null $extension     Расширение файла без точки
 * @property-read int|null    $width         Ширина файла (для image/*)
 * @property-read int|null    $height        Высота файла (для image/*)
 */
class FileUploadResult
{
    const STAGE_UPLOADED  = 'uploaded';
    const STAGE_PROCESSED = 'processed';

    /**
     * @param bool $isSuccess - успешность операции
     * @param string|null   $stage          - этап: 'uploaded' (проверка is_uploaded_file) или 'processed' (файл перемещён в storage)
     * @param string|null   $originalName   - оригинальное имя файла
     * @param string|null   $savedName      - имя файла в хранилище (сгенерированное на этапе process) - с расширением и точкой
     * @param string|null   $path           - полный путь от корня ФС к каталогу с файлом (опция targetPath или метод setTargetPath())
     * @param string|null   $fullPath       - полный путь от корня ФС к каталогу с файлом + имя файла. Пригодно для файловых операций с файлом в обход класса
     * @param string|null   $mimeType       - MIME-тип файла
     * @param int|null      $size           - размер файла
     * @param string|null   $lastError      - последняя ошибка
     * @param array         $errors         - массив со списком ошибок
     * @param string|null   $radix          - корень имени файла
     * @param string|null   $extension      - расширение файла без точки
     * @param int|null      $width          - ширина файла (для image/*)
     * @param int|null      $height         - высота файла (для image/*)
     */
    public function __construct(
        public readonly bool $isSuccess,
        public readonly ?string $stage = null,
        public readonly ?string $originalName = null,
        public readonly ?string $savedName = null,
        public readonly ?string $path = null,
        public readonly ?string $fullPath = null,
        public readonly ?string $mimeType = null,
        public readonly ?int $size = null,
        public readonly ?string $lastError = null,
        public readonly array $errors = [],
        public readonly ?string $radix = null,
        public readonly ?string $extension = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null
    ) {}

    public function toJson(bool $pretty = false): string
    {
        $data = [
            'isSuccess' => $this->isSuccess,
            'stage' => $this->stage,
            'originalName' => $this->originalName,
            'savedName' => $this->savedName,
            'fullPath' => $this->fullPath,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'lastError' => $this->lastError,
            'errors' => $this->errors,
            'radix' => $this->radix,
            'extension' => $this->extension,
            'width' => $this->width,
            'height' => $this->height
        ];

        $flags = JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($data, $flags);
    }

    public function toArray(): array
    {
        return [
            'isSuccess' => $this->isSuccess,
            'stage' => $this->stage,
            'originalName' => $this->originalName,
            'savedName' => $this->savedName,
            'fullPath' => $this->fullPath,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'lastError' => $this->lastError,
            'errors' => $this->errors,
            'radix' => $this->radix,
            'extension' => $this->extension,
            'width' => $this->width,
            'height' => $this->height
        ];
    }

    public function __toString(): string
    {
        return $this->toJson();
    }
}