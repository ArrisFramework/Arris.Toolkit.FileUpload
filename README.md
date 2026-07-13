# FileUpload - PHP8 File Upload Library

Библиотека для загрузки файлов с валидацией, конвертацией изображений и Fluent Interface.

## Требования

- PHP 8.2+
- ext-fileinfo
- ext-gd (опционально, для конверсии изображений)

## Установка

```bash
composer require karelwintersky/arris.php-file-upload
```

## Быстрый старт

```php
use Arris\Toolkit\FileUpload;

$upload = FileUpload::fromFile($_FILES['photo'], 0)
    ->setTargetPath('/var/www/uploads/')
    ->allowMimeTypes(['image/jpeg', 'image/png'])
    ->setMaxFileSize(5 * 1024 * 1024);

$result = $upload->process();

if ($result->isSuccess) {
    echo $result->fullPath;
} else {
    echo implode(', ', $result->errors);
}
```

## Два этапа загрузки

### 1. `uploaded()` — проверка первичной загрузки

Проверяет `is_uploaded_file()` — попал ли файл на сервер через HTTP.

```php
$upload = FileUpload::fromFile($_FILES['photo'], 0);

$check = $upload->uploaded();

if (!$check->isSuccess) {
    // Файл не загружен — fast fail
    echo $check->errors[0]; // "Файл не был загружен"
}
```

### 2. `process()` — валидация, конверсия, сохранение

```php
$result = $upload->process();

if ($result->isSuccess) {
    echo $result->savedName;    // "2024_01_15__a1b2c3d4.jpg"
    echo $result->fullPath;     // "/var/www/uploads/2024_01_15__a1b2c3d4.jpg"
    echo $result->radix;        // "2024_01_15__a1b2c3d4"
    echo $result->mimeType;     // "image/jpeg"
    echo $result->size;         // 102400
    echo $result->width;        // 1920 (для изображений)
    echo $result->height;       // 1080 (для изображений)
}
```

## Конфигурация

### Дефолтный конфиг (один раз при бутстрапе)

```php
FileUpload::setDefaultConfig([
    'targetPath'        => '/var/www/uploads/',
    'allowedMimeTypes'  => ['image/jpeg', 'image/png', 'image/webp'],
    'maxFileSize'       => 10 * 1024 * 1024,
    'filenameGenerator' => function (string $originalName, array $file) {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return date('Y_m_d_') . uniqid(more_entropy: true) . ".{$ext}";
    },
    'throwExceptions'   => false,
]);
```

### Опция через `applyOption()`

```php
FileUpload::applyOption('targetPath', '/var/www/photos/');
FileUpload::applyOption('targetMimeType', 'image/webp');
FileUpload::applyOption('targetImageQuality', 85);
```

### Fluent-конфигурация на инстансе

```php
$upload = FileUpload::fromFile($_FILES['photo'], 0)
    ->setTargetPath('/var/www/uploads/')
    ->allowMimeTypes(['image/jpeg', 'image/png'])
    ->setMaxFileSize(5 * 1024 * 1024)
    ->setTargetMimeType('image/webp', 85)
    ->setFilenameGenerator(fn($name) => uniqid() . '.' . pathinfo($name, PATHINFO_EXTENSION));
```

## Валидаторы

Вот это - внутренние валидаторы. 

```php
>allowMimeTypes(['image/jpeg', 'image/png'])
->setMaxFileSize(5 * 1024 * 1024)
->setMinFileSize(5 * 1024 * 1024)
```

Можно определить кастомные валидаторы - функции-коллбэки, которые принимают запись о файле и возвращают:

- `true` - валидация пройдена
- `false` - валидация не пройдена, в массив ошибок запишем дефолтную строку ошибки "ошибка валидации файла"
- строка - валидация не пройдена, в массив ошибок запишем указанную строку.

Валидаторы исполняются последовательно, сначала встроенные, потом кастомные. 

```php
$upload = FileUpload::fromFile($_FILES['document'], 0)
    ->setTargetPath('/var/www/docs/')
    ->addValidator(function (array $file): bool|string {
        if ($file['size'] < 1024) {
            return 'Файл слишком маленький (минимум 1KB)';
        }
        return true;
    })
    ->addValidator(function (array $file): bool|string {
        if (preg_match('/[^a-zA-Z0-9._-]/', $file['name'])) {
            return 'Имя файла содержит недопустимые символы';
        }
        return true;
    })
    ->addValidator(function(array $file): bool|string {
        // Проверка размера изображения (если это изображение)
        $mimeType = mime_content_type($file['tmp_name']);
        if (str_starts_with($mimeType, 'image/')) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo[0] < 100 || $imageInfo[1] < 100) {
                return 'Изображение слишком маленькое';
            }
        }
        return true;
    });;

$result = $upload->process();
```

## Конверсия изображений

Конвертирует изображение из одного формата в другой при перемещении в storage.

Для этого нужно указать целевой mime-тип и качество.

@todo: 3 параметр force, который заставляет применить конвертор даже если целевой mime-тип совпадает с исходным. 

```php
$upload = FileUpload::fromFile($_FILES['photo'], 0)
    ->setTargetPath('/var/www/images/')
    ->allowMimeTypes(['image/jpeg', 'image/png'])
    ->setTargetMimeType('image/webp', 85); // формат + качество

$result = $upload->process();

if ($result->isSuccess) {
    // Исходное: photo.png → Сохранённое: <radix>.webp
    echo $result->extension; // "webp"
}
```

Поддерживаемые форматы: JPEG, PNG, GIF, WebP, BMP.

### Кастомный конвертер

```php
FileUpload::applyOption('conversionCallback', function (
    string $sourcePath,
    string $targetPath,
    string $targetMime,
    int $quality
): bool {
    // Своя логика конвертации
    return copy($sourcePath, $targetPath);
});
```

## Обработка ошибок

### Тихий режим (по умолчанию)

```php
$result = $upload->process();

if (!$result->isSuccess) {
    echo $result->lastError;
    print_r($result->errors);
}
```

### Режим исключений

```php
use Arris\Toolkit\FileUploadException;

FileUpload::applyOption('throwExceptions', true);

try {
    $result = $upload->process();
} catch (FileUploadException $e) {
    echo $e->getMessage();
    print_r($e->getErrors());
}
```

## Множественная загрузка

```php
$photoKeys = array_keys($_FILES['photos']['tmp_name']);

foreach ($photoKeys as $photoId) {
    $upload = FileUpload::fromFile($_FILES['photos'], $photoId);

    $check = $upload->uploaded();
    if (!$check->isSuccess) {
        continue;
    }

    $result = $upload->process();
    if ($result->isSuccess) {
        // сохраняем $result->radix, $result->mimeType, etc.
    }
}
```

## FileUploadResult

Объект возвращаемый `uploaded()` и `process()`.

| Поле | Тип | Описание |
|------|-----|----------|
| `isSuccess` | `bool` | Успешность операции |
| `stage` | `string\|null` | `'uploaded'` или `'processed'` |
| `originalName` | `string\|null` | Оригинальное имя файла |
| `savedName` | `string\|null` | Имя файла в storage |
| `path` | `string\|null` | Путь к каталогу storage |
| `fullPath` | `string\|null` | Полный путь к файлу |
| `mimeType` | `string\|null` | MIME-тип |
| `size` | `int\|null` | Размер в байтах |
| `lastError` | `string\|null` | Последняя ошибка |
| `errors` | `array` | Массив ошибок |
| `radix` | `string\|null` | Имя файла без расширения |
| `extension` | `string\|null` | Расширение без точки |
| `width` | `int\|null` | Ширина (image/*) |
| `height` | `int\|null` | Высота (image/*) |

```php
// Сериализация
$result->toJson();      // JSON строка
$result->toJson(true);  // JSON с форматированием
$result->toArray();     // PHP массив
(string) $result;       // JSON через __toString
```

## Доступные опции

| Опция | Тип | Описание |
|-------|-----|----------|
| `targetPath` | `string` | Каталог для сохранения |
| `allowedMimeTypes` | `array` | Разрешённые MIME-типы |
| `maxFileSize` | `int` | Максимальный размер (байты), 0 = без ограничений |
| `filenameGenerator` | `callable` | Генератор имени файла `fn(string $name, array $file): string` |
| `throwExceptions` | `bool` | Бросать `FileUploadException` вместо возврата ошибки |
| `validators` | `array` | Массив callable-валидаторов |
| `targetMimeType` | `string` | Целевой MIME-тип для конверсии |
| `targetImageQuality` | `int` | Качество конверсии (0-100) |

## Лицензия

MIT License
