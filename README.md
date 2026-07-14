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

Проверяет `is_uploaded_file()`, выполняет полную валидацию (MIME, размер, кастомные валидаторы).
Возвращает `FileUploadResult` с stage=`uploaded`. На этапе `uploaded` уже доступны `size` и `mimeType`.

```php
$upload = FileUpload::fromFile($_FILES['photo'], 0);

$check = $upload->uploaded();

if (!$check->isSuccess) {
    echo $check->errors[0];
    echo $check->size; // размер файла доступен даже при ошибке
}
```

### 2. `process()` — конверсия, сохранение

Если `uploaded()` уже вызван и прошёл успешно — `process()` **пропускает** валидацию (флаг `validated`).
Если вызван напрямую — выполняет валидацию сам.

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
    'minFileSize'       => 1024,
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
FileUpload::applyOption('minFileSize', 1024);
FileUpload::applyOption('locale', 'en');
```

### Fluent-конфигурация на инстансе

```php
$upload = FileUpload::fromFile($_FILES['photo'], 0)
    ->setTargetPath('/var/www/uploads/')
    ->allowMimeTypes(['image/jpeg', 'image/png'])
    ->setMaxFileSize(5 * 1024 * 1024)
    ->setMinFileSize(1024)
    ->setTargetMimeType('image/webp', 85)
    ->setLocale('en')
    ->setFilenameGenerator(fn($name) => uniqid() . '.' . pathinfo($name, PATHINFO_EXTENSION));
```

## Валидация

### Встроенные валидаторы

```php
$upload = FileUpload::fromFile($_FILES['photo'], 0)
    ->allowMimeTypes(['image/jpeg', 'image/png'])
    ->setMaxFileSize(5 * 1024 * 1024)
    ->setMinFileSize(1024);
```

### Кастомные валидаторы

Функции-коллбэки, которые принимают массив файла и возвращают:

- `true` — валидация пройдена
- `false` — валидация не пройдена, в ошибки запишется дефолтное сообщение "Ошибка валидации файла"
- строка — валидация не пройдена, в ошибки запишется указанная строка

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
    });
```

### Порядок и collect-all

Валидация выполняется в следующем порядке:

1. **Прекондишины** (fail-fast): файл не задан → не загружен через HTTP → PHP upload-ошибка
2. **Встроенные валидаторы**: MIME-тип → минимальный размер → максимальный размер
3. **Кастомные валидаторы**: все по порядку

Встроенные и кастомные валидаторы работают в режиме **collect-all**: все проверки выполняются, все ошибки собираются. Пользователь видит **все** проблемы сразу, а не только первую попавшуюся.

```php
// Пример: файл слишком маленький + неверный MIME — обе ошибки в одном ответе
FileUpload::applyOption('allowedMimeTypes', ['image/png']);
FileUpload::applyOption('minFileSize', 300 * 1024);

$result = $upload->uploaded();
// $result->errors = ['Файл слишком маленький', 'Недопустимый тип файла: image/jpeg']
```

## Конверсия изображений

Конвертирует изображение из одного формата в другой при перемещении в storage.

Для этого нужно указать целевой mime-тип и качество. Третий параметр `$force` заставляет применить конвертер даже если целевой mime-тип совпадает с исходным — это позволяет приводить загруженные фотографии к общему стандарту (пережатие).

```php
$upload = FileUpload::fromFile($_FILES['photo'], 0)
    ->setTargetPath('/var/www/images/')
    ->allowMimeTypes(['image/jpeg', 'image/png'])
    ->setTargetMimeType('image/webp', 85);

$result = $upload->process();

if ($result->isSuccess) {
    echo $result->extension; // "webp"
}
```

### Принудительная конвертация (`$force`)

```php
// JPEG → JPEG, но с пережатием до качества 75
$upload->setTargetMimeType('image/jpeg', 75, true);
```

### Кастомный конвертер

```php
FileUpload::applyOption('conversionCallback', function (
    string $sourcePath,
    string $targetPath,
    string $targetMime,
    int $quality
): bool {
    return copy($sourcePath, $targetPath);
});
```

### Поддерживаемые форматы

| Формат | Источник | Цель |
|--------|----------|------|
| JPEG   | yes      | yes  |
| PNG    | yes      | yes  |
| GIF    | yes      | yes  |
| WebP   | yes      | yes  |
| BMP    | yes      | —    |

## Система ошибок

### Коды ошибок

Каждая ошибка имеет код `FileUploadErrorCode` (backed enum). Доступны через `getErrorStack()`:

```php
$stack = $upload->getErrorStack();
// [
//     ['code' => ErrorCode::FILE_TOO_LARGE, 'params' => []],
//     ['code' => ErrorCode::VALIDATOR_FAILED, 'params' => ['message' => 'Файл повреждён']],
// ]

foreach ($stack as $entry) {
    echo $entry['code']->value; // 'file_too_large'
}
```

### Трансляция сообщений

`getErrors()` возвращает массив человекочитаемых строк (через `FileUploadErrorMessages`):

```php
$errors = $upload->getErrors();
// ['Файл слишком большой', 'Файл повреждён']
```

### Кастомизация сообщений

```php
use Arris\Toolkit\FileUpload\ErrorCode;
use Arris\Toolkit\FileUpload\ErrorMessages;

// Одно сообщение
ErrorMessages::setMessage(
    ErrorCode::FILE_TOO_LARGE,
    'Максимум 10 МБ!'
);

// Пакетная замена (удобно для i18n)
ErrorMessages::setMessages([
    'file_too_large'  => 'Maximum 10 MB',
    'file_too_small'  => 'Minimum 1 KB',
    'invalid_mime_type' => 'Unsupported file type: {mime_type}',
    'validator_failed'  => '{message}',
]);
```

Параметры в шаблонах: `{mime_type}`, `{message}` — подставляются из params.

### Локализация (i18n)

Встроенные locales: `ru` (по умолчанию) и `en`.

```php
use Arris\Toolkit\FileUpload;

// На инстансе
$upload = FileUpload::fromFile($_FILES['file'])
    ->setLocale('en');

// Глобально через конфиг
FileUpload::setDefaultConfig([
    'locale' => 'en',
]);

// Или через applyOption
FileUpload::applyOption('locale', 'en');

// Принудительно для текущего результата
$errors = $upload->setLocale('ru')->getErrors();
```

### Обработка ошибок

#### Тихий режим (по умолчанию)

```php
$result = $upload->process();

if (!$result->isSuccess) {
    echo $result->lastError;
    print_r($result->errors);
}
```

#### Режим исключений

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
| `maxFileSize` | `int` | Максимальный размер (байты) |
| `minFileSize` | `int` | Минимальный размер (байты) |
| `filenameGenerator` | `callable` | Генератор имени файла `fn(string $name, array $file): string` |
| `throwExceptions` | `bool` | Бросать `FileUploadException` вместо возврата ошибки |
| `validators` | `array` | Массив callable-валидаторов |
| `targetMimeType` | `string` | Целевой MIME-тип для конверсии |
| `targetImageQuality` | `int` | Качество конверсии (0-100) |
| `locale` | `string` | Локаль для сообщений ошибок (`'ru'` или `'en'`) |

## Вспомогательные классы

Все вспомогательные классы находятся в неймспейсе `Arris\Toolkit\FileUpload\*`.

### ImageConvertor

Конвертирует изображения между форматами (GD). Fluent API:

```php
use Arris\Toolkit\FileUpload\ImageConvertor;

ImageConvertor::from('/path/to/photo.jpg')
    ->toWebP(quality: 85)
    ->save('/path/to/output/');

ImageConvertor::from('/path/to/photo.jpg')
    ->toPng(compression: 9)
    ->save('/path/to/output/');

ImageConvertor::from('/path/to/photo.gif')
    ->toPng(preserveAlpha: true)
    ->save('/path/to/output/');
```

Поддерживаемые конверсии: JPEG, PNG, GIF, WebP, BMP → JPEG/PNG/GIF/WebP.

### MediaProbe

Обёртка над `ffprobe` для получения метаданных медиафайлов:

```php
use Arris\Toolkit\FileUpload\MediaProbe;

$info = MediaProbe::probe('/path/to/video.mp4');

echo $info->width;     // 1920
echo $info->height;    // 1080
echo $info->codec;     // "h264"
echo $info->duration;  // 125.4
echo $info->isVideo;   // true
echo $info->isAudio;   // false
```

Возвращает `MediaProbeResult` (readonly value object) или `null` при ошибке.

### Helper

Статический хелпер для работы с размерами файлов и лимитами загрузки:

```php
use Arris\Toolkit\FileUpload\Helper;

// Конвертация строкового размера в байты
Helper::returnBytes('64M');    // 67108864
Helper::returnBytes('1.5G');   // 1610612736
Helper::returnBytes('1024K');  // 1048576
Helper::returnBytes(1024);     // 1024

// Значение php.ini директивы в байтах
Helper::getIniValue('upload_max_filesize');  // например 20971520 (20M)
Helper::getIniValue('post_max_size');        // например 8388608 (8M)

// Вычисление реального лимита загрузки
$limits = Helper::getUploadLimits('64M');

// $limits['POST_MAX_SIZE']   — post_max_size в байтах
// $limits['UPLOAD_MAX_SIZE'] — upload_max_filesize в байтах
// $limits['CONFIG_MAX_SIZE'] — прикладной лимит (64M) в байтах
// $limits['REAL_MAX_SIZE']   — минимум из трёх
// $limits['IS_WRONG_SIZE']   — true если конфиг превышает физические лимиты
```

## Лицензия

MIT License
