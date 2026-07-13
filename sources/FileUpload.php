<?php

namespace Arris\Toolkit;

use InvalidArgumentException;

class FileUpload
{
    const validOptions = [
        'targetPath',
        'allowedMimeTypes',
        'maxFileSize',
        'minFileSize',
        'filenameGenerator',
        'throwExceptions',
        'validators'
    ];

    private array $file = [];
    private ?string $targetPath = null;
    private array $allowedMimeTypes = [];
    private ?int $maxFileSize = null;
    private ?int $minFileSize = null;
    private array $customValidators = [];
    private mixed $filenameGenerator = null;
    private array $errorStack = [];
    private bool $throwExceptions = false;
    private ?int $fileIndex = null;
    private bool $validated = false;

    // Поля для конфигурации по умолчанию
    private static ?FileUpload $instance = null;
    private static array $defaultConfig = [];

    /**
     * Коллбэк конвертации (кастомный метод)
     * @var mixed|null
     */
    private mixed $conversionCallback = null;

    /**
     * Целевой MIME-тип для конверсии
     * @var string|null
     */
    private ?string $targetMimeType = null;

    /**
     * Целевой compression quality
     * 0..100 JPEG/WEBP
     * 0..10 PNG
     * @var int
     */
    private int $targetImageQuality = 90;


    /**
     * @param array|null $file Массив файла из $_FILES или null
     * @param int|null $index Индекс файла для множественных загрузок
     * @param array $config Конфигурация
     */
    public function __construct(?array $file = null, ?int $index = null, array $config = [])
    {
        if ($file !== null) {
            $this->file = $this->extractFileFromArray($file, $index);
            $this->fileIndex = $index;
        }

        // Применяем конфигурацию по умолчанию
        $this->applyConfig(array_merge(self::$defaultConfig, $config));
    }

    /**
     * Извлекает конкретный файл из массива множественных файлов
     */
    private function extractFileFromArray(array $fileArray, ?int $index): array
    {
        // Проверяем, является ли это массивом множественных файлов
        if (isset($fileArray['name']) && is_array($fileArray['name'])) {
            // Это множественная загрузка
            if ($index === null) {
                // Если индекс не указан, берем первый файл
                $keys = array_keys($fileArray['name']);
                $index = $keys[0] ?? null;
            }

            if ($index !== null && isset($fileArray['name'][$index])) {
                return [
                    'name' => $fileArray['name'][$index],
                    'type' => $fileArray['type'][$index] ?? '',
                    'tmp_name' => $fileArray['tmp_name'][$index] ?? '',
                    'error' => $fileArray['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $fileArray['size'][$index] ?? 0,
                    'full_path' => $fileArray['full_path'][$index] ?? $fileArray['name'][$index],
                ];
            }

            return [];
        }

        // Это одиночный файл
        return $fileArray;
    }

    /**
     * Устанавливает дефолтный конфиг
     * @param array $config
     * @return void
     */
    public static function setDefaultConfig(array $config): void
    {
        foreach ($config as $key => $value) {
            self::applyOption($key, $value);
        }
    }


    /**
     * Добавляет опцию к дефолтному конфигу
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public static function applyOption(string $name, mixed $value): void
    {
        match($name) {
            'targetPath'        =>  self::$defaultConfig['targetPath'] = rtrim($value, '/') . '/',
            'allowedMimeTypes'  =>  self::$defaultConfig['allowedMimeTypes'] = $value,
            'maxFileSize'       =>  self::$defaultConfig['maxFileSize'] = $value,
            'minFileSize'       =>  self::$defaultConfig['minFileSize'] = $value,
            'filenameGenerator' =>  self::$defaultConfig['filenameGenerator'] = is_callable($value) ? $value : null,
            'throwExceptions'   =>  self::$defaultConfig['throwExceptions'] = (bool)$value,
            'validators'        =>  self::$defaultConfig['validators'] = is_array($value) ? $value : [],
            'targetMimeType'    =>  self::$defaultConfig['targetMimeType'] = $value,
            'targetImageQuality'=>  self::$defaultConfig['targetImageQuality'] = $value,
            default => throw new InvalidArgumentException("Unknown option: {$name}")
        };
    }

    public static function getInstance(bool $really_new_instance = false): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Создает экземпляр FileUpload из массива $_FILES
     *
     * @param array $file Массив файла из $_FILES
     * @param int|null $index Индекс для множественных файлов
     */
    public static function fromFile(array $file, ?int $index = null): self
    {
        $instance = self::getInstance();
        $newInstance = clone $instance;
        $newInstance->file = $instance->extractFileFromArray($file, $index);
        $newInstance->fileIndex = $index;
        $newInstance->errorStack = [];
        $newInstance->validated = false;
        return $newInstance;
    }

    private function applyConfig(array $config): void
    {
        if (isset($config['targetPath'])) {
            $this->setTargetPath($config['targetPath']);
        }

        if (isset($config['allowedMimeTypes'])) {
            $this->allowMimeTypes($config['allowedMimeTypes']);
        }

        if (isset($config['maxFileSize'])) {
            $this->setMaxFileSize($config['maxFileSize']);
        }

        if (isset($config['minFileSize'])) {
            $this->setMinFileSize($config['minFileSize']);
        }

        if (isset($config['filenameGenerator']) && is_callable($config['filenameGenerator'])) {
            $this->setFilenameGenerator($config['filenameGenerator']);
        }

        if (isset($config['throwExceptions'])) {
            $this->throwExceptions($config['throwExceptions']);
        }

        if (isset($config['validators']) && is_array($config['validators'])) {
            foreach ($config['validators'] as $validator) {
                if (is_callable($validator)) {
                    $this->addValidator($validator);
                }
            }
        }

        if (isset($config['targetMimeType'])) {
            $this->setTargetMimeType($config['targetMimeType']);
        }

        if (isset($config['targetImageQuality'])) {
            $this->targetImageQuality = $config['targetImageQuality'];
        }
    }

    // ─── Error stack helpers ──────────────────────────────────────────────

    /**
     * Добавляет ошибку в стек (хранение по кодам).
     */
    private function pushError(FileUploadErrorCode $code, array $params = []): void
    {
        $this->errorStack[] = ['code' => $code, 'params' => $params];
    }

    /**
     * Резолвит стек ошибок в массив человекочитаемых строк.
     */
    private function resolveErrors(): array
    {
        return array_map(
            fn(array $entry) => FileUploadErrorMessages::resolve($entry['code'], $entry['params'] ?? []),
            $this->errorStack
        );
    }

    /**
     * Маппит PHP UPLOAD_ERR_* код в FileUploadErrorCode.
     */
    private static function mapUploadErrorCode(int $errorCode): FileUploadErrorCode
    {
        return match($errorCode) {
            UPLOAD_ERR_INI_SIZE  => FileUploadErrorCode::UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => FileUploadErrorCode::UPLOAD_ERR_FORM_SIZE,
            UPLOAD_ERR_PARTIAL   => FileUploadErrorCode::UPLOAD_ERR_PARTIAL,
            UPLOAD_ERR_NO_FILE   => FileUploadErrorCode::UPLOAD_ERR_NO_FILE,
            UPLOAD_ERR_NO_TMP_DIR => FileUploadErrorCode::UPLOAD_ERR_NO_TMP_DIR,
            UPLOAD_ERR_CANT_WRITE => FileUploadErrorCode::UPLOAD_ERR_CANT_WRITE,
            UPLOAD_ERR_EXTENSION => FileUploadErrorCode::UPLOAD_ERR_EXTENSION,
            default              => FileUploadErrorCode::NOT_UPLOADED,
        };
    }

    // ─── Upload stages ───────────────────────────────────────────────────

    /**
     * Alias
     * @return FileUploadResult
     */
    public function is_uploaded(): FileUploadResult
    {
        return $this->uploaded();
    }

    /**
     * Проверяет, был ли файл успешно загружен во временный каталог.
     * Выполняет полную валидацию: MIME, размер, кастомные валидаторы.
     * Возвращает FileUploadResult с stage='uploaded'.
     */
    public function uploaded(): FileUploadResult
    {
        $this->errorStack = [];

        if (empty($this->file)) {
            $this->pushError(FileUploadErrorCode::FILE_NOT_SET);
            $resolved = $this->resolveErrors();
            return new FileUploadResult(
                isSuccess: false,
                stage: FileUploadResult::STAGE_UPLOADED,
                errors: $resolved
            );
        }

        if (!isset($this->file['tmp_name']) || !is_uploaded_file($this->file['tmp_name'])) {
            $this->pushError(FileUploadErrorCode::NOT_UPLOADED);
            $resolved = $this->resolveErrors();
            return new FileUploadResult(
                isSuccess: false,
                stage: FileUploadResult::STAGE_UPLOADED,
                originalName: $this->file['name'] ?? null,
                errors: $resolved
            );
        }

        $error = $this->file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            $code = self::mapUploadErrorCode($error);
            $this->pushError($code);
            $resolved = $this->resolveErrors();
            return new FileUploadResult(
                isSuccess: false,
                stage: FileUploadResult::STAGE_UPLOADED,
                originalName: $this->file['name'] ?? null,
                lastError: end($resolved),
                errors: $resolved
            );
        }

        // Полная валидация: MIME, размер, кастомные валидаторы
        if (!$this->validate()) {
            $resolved = $this->resolveErrors();
            return new FileUploadResult(
                isSuccess: false,
                stage: FileUploadResult::STAGE_UPLOADED,
                originalName: $this->file['name'] ?? null,
                lastError: end($resolved) ?: null,
                errors: $resolved
            );
        }

        $this->validated = true;

        return new FileUploadResult(
            isSuccess: true,
            stage: FileUploadResult::STAGE_UPLOADED,
            originalName: $this->file['name'] ?? null,
            mimeType: mime_content_type($this->file['tmp_name'])
        );
    }

    // ─── Fluent setters ──────────────────────────────────────────────────

    public function setTargetPath(string $path): self
    {
        $this->targetPath = rtrim($path, '/') . '/';
        return $this;
    }

    public function allowMimeTypes(array $mimeTypes): self
    {
        $this->allowedMimeTypes = $mimeTypes;
        return $this;
    }

    public function setMaxFileSize(int $bytes): self
    {
        if ($bytes === 0) {
            $bytes = PHP_INT_MAX;
        }
        $this->maxFileSize = $bytes;
        return $this;
    }

    public function setMinFileSize(int $bytes): self
    {
        $this->minFileSize = $bytes;
        return $this;
    }

    public function addValidator(callable $validator): self
    {
        $this->customValidators[] = $validator;
        return $this;
    }

    public function setFilenameGenerator(callable $generator): self
    {
        $this->filenameGenerator = $generator;
        return $this;
    }

    public function throwExceptions(bool $throw = true): self
    {
        $this->throwExceptions = $throw;
        return $this;
    }

    // ─── Validation ──────────────────────────────────────────────────────

    public function validate(): bool
    {
        $this->errorStack = [];

        // ── Прекондишины: fail-fast (нет файла → нечего проверять) ──

        if (empty($this->file)) {
            $this->pushError(FileUploadErrorCode::FILE_NOT_SET);
            return false;
        }

        if (!isset($this->file['tmp_name']) || !is_uploaded_file($this->file['tmp_name'])) {
            $this->pushError(FileUploadErrorCode::NOT_UPLOADED);
            return false;
        }

        $error = $this->file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            $this->pushError(self::mapUploadErrorCode($error));
            return false;
        }

        $valid = true;

        // ── Встроенные валидаторы: collect-all ──

        if (!empty($this->allowedMimeTypes)) {
            $mimeType = mime_content_type($this->file['tmp_name']);
            if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
                $this->pushError(FileUploadErrorCode::INVALID_MIME_TYPE, ['mime_type' => $mimeType]);
                $valid = false;
            }
        }

        $fileSize = $this->file['size'] ?? 0;
        if ($this->minFileSize !== null && $fileSize < $this->minFileSize) {
            $this->pushError(FileUploadErrorCode::FILE_TOO_SMALL);
            $valid = false;
        }

        if ($this->maxFileSize !== null && $fileSize > $this->maxFileSize) {
            $this->pushError(FileUploadErrorCode::FILE_TOO_LARGE);
            $valid = false;
        }

        foreach ($this->customValidators as $validator) {
            $result = $validator($this->file);
            if ($result !== true) {
                $params = is_string($result)
                    ? ['message' => $result]
                    : ['message' => 'Ошибка валидации файла'];
                $this->pushError(FileUploadErrorCode::VALIDATOR_FAILED, $params);
                $valid = false;
            }
        }

        return $valid;
    }

    // ─── Process ─────────────────────────────────────────────────────────

    /**
     * @throws FileUploadException
     */
    public function process(): FileUploadResult
    {
        try {
            if (!$this->validated && !$this->validate()) {
                return $this->createFailureResult();
            }

            if ($this->targetPath === null) {
                $this->pushError(FileUploadErrorCode::TARGET_PATH_NOT_SET);
                return $this->createFailureResult();
            }

            if (!is_dir($this->targetPath)) {
                if (!mkdir($this->targetPath, 0755, true)) {
                    $this->pushError(FileUploadErrorCode::TARGET_DIR_CREATE_FAILED);
                    return $this->createFailureResult();
                }
            }

            $sourceMimeType = mime_content_type($this->file['tmp_name']);
            $savedFilename = $this->generateFilename($this->file['name']);

            $fullPath = $this->targetPath . $savedFilename;

            $needsConversion = $this->targetMimeType !== null &&
                $this->targetMimeType !== $sourceMimeType &&
                str_starts_with($sourceMimeType, 'image/') &&
                str_starts_with($this->targetMimeType, 'image/');

            if ($needsConversion) {
                $targetPath = $this->targetPath . $this->changeExtension($savedFilename, $this->targetMimeType);

                if (!move_uploaded_file($this->file['tmp_name'], $targetPath . '.tmp')) {
                    $this->pushError(FileUploadErrorCode::FILE_MOVE_FAILED);
                    return $this->createFailureResult();
                }

                if ($this->conversionCallback) {
                    $success = ($this->conversionCallback)($targetPath . '.tmp', $targetPath, $this->targetMimeType, $this->targetImageQuality);
                } else {
                    $success = ImageConvertor::convert(
                        $targetPath . '.tmp',
                        $targetPath,
                        $this->targetMimeType,
                        $this->targetImageQuality
                    );
                }

                @unlink($targetPath . '.tmp');

                if (!$success) {
                    $this->pushError(FileUploadErrorCode::CONVERSION_FAILED);
                    return $this->createFailureResult();
                }

                $savedFilename = $this->changeExtension($savedFilename, $this->targetMimeType);
                $finalPath = $targetPath;
                $finalMimeType = $this->targetMimeType;
            } else {
                // Обычное перемещение без конвертации
                $finalPath = $this->targetPath . $savedFilename;
                if (!move_uploaded_file($this->file['tmp_name'], $finalPath)) {
                    $this->pushError(FileUploadErrorCode::FILE_MOVE_FAILED);
                    return $this->createFailureResult();
                }
                $finalMimeType = $sourceMimeType;
            }

            $fileInfo = pathinfo($savedFilename);
            $radix = $fileInfo['filename'];
            $extension = $fileInfo['extension'] ?? null;

            [$width, $height] = $this->getImageDimensions($finalPath, $finalMimeType);

            return new FileUploadResult(
                isSuccess: true,
                stage: FileUploadResult::STAGE_PROCESSED,
                originalName: $this->file['name'],
                savedName: $savedFilename,
                path: $this->targetPath,
                fullPath: $finalPath,
                mimeType: $finalMimeType,
                size: filesize($finalPath),
                errors: [],
                radix: $radix,
                extension: $extension,
                width: $width,
                height: $height
            );

        } catch (\Throwable $e) {
            $this->pushError(FileUploadErrorCode::EXCEPTION, ['message' => $e->getMessage()]);
            if ($this->throwExceptions) {
                throw new FileUploadException(
                    FileUploadErrorMessages::resolve(FileUploadErrorCode::EXCEPTION, ['message' => $e->getMessage()]),
                    $this->resolveErrors(),
                    0,
                    $e
                );
            }
            return $this->createFailureResult();
        }
    }

    // ─── Accessors ───────────────────────────────────────────────────────

    /**
     * Возвращает ошибки в виде человекочитаемых строк (через транслятор).
     */
    public function getErrors(): array
    {
        return $this->resolveErrors();
    }

    /**
     * Возвращает сырой стек ошибок (коды + параметры).
     *
     * @return array<array{code: FileUploadErrorCode, params: array}>
     */
    public function getErrorStack(): array
    {
        return $this->errorStack;
    }

    public function getFile(): array
    {
        return $this->file;
    }

    public function getFileIndex(): ?int
    {
        return $this->fileIndex;
    }

    /**
     * Создает новый экземпляр с другим файлом
     */
    public function withFile(array $file, ?int $index = null): self
    {
        $newInstance = clone $this;
        $newInstance->file = $this->extractFileFromArray($file, $index);
        $newInstance->fileIndex = $index;
        $newInstance->errorStack = [];
        $newInstance->validated = false;
        return $newInstance;
    }

    /**
     * Устанавливает целевой MIME-тип для конверсии
     *
     * @param string $mimeType
     * @param int $compression_quality
     * @return $this
     */
    public function setTargetMimeType(string $mimeType, int $compression_quality = 90): self
    {
        $this->targetMimeType = $mimeType;
        $this->targetImageQuality = max(0, min($compression_quality, 100));
        return $this;
    }

    // ─── Private helpers ─────────────────────────────────────────────────

    private function createFailureResult(): FileUploadResult
    {
        $resolved = $this->resolveErrors();

        if ($this->throwExceptions) {
            throw new FileUploadException(
                $resolved[0] ?? 'Ошибка загрузки файла',
                $resolved
            );
        }

        return new FileUploadResult(
            isSuccess: false,
            stage: FileUploadResult::STAGE_PROCESSED,
            originalName: $this->file['name'] ?? null,
            lastError: end($resolved) ?: null,
            errors: $resolved
        );
    }

    private function generateFilename(string $originalName): string
    {
        if ($this->filenameGenerator !== null) {
            return ($this->filenameGenerator)($originalName, $this->file);
        }

        $targetFile = $this->targetPath . $originalName;

        if (!file_exists($targetFile)) {
            return $originalName;
        }

        $info = pathinfo($originalName);
        $filename = $info['filename'];
        $extension = $info['extension'] ?? '';

        $counter = 1;
        do {
            $suffix = '_' . $counter;
            $newFilename = $filename . $suffix . ($extension ? '.' . $extension : '');
            $newFilepath = $this->targetPath . $newFilename;
            $counter++;
        } while (file_exists($newFilepath));

        return $newFilename;
    }

    private function getImageDimensions(string $filepath, string $mimeType): array
    {
        if (str_starts_with($mimeType, 'image/')) {
            $imageInfo = @getimagesize($filepath);
            if ($imageInfo !== false) {
                return [$imageInfo[0], $imageInfo[1]];
            }
        }
        return [null, null];
    }

    /**
     * @param string $filename
     * @param string $mimeType
     * @return string
     */
    private function changeExtension(string $filename, string $mimeType): string
    {
        $fileInfo = pathinfo($filename);
        $filenameWithoutExt = $fileInfo['filename'];

        $extension = match($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => $fileInfo['extension'] ?? 'img'
        };

        return $filenameWithoutExt . '.' . $extension;
    }

}
