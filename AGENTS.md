# AGENTS.md — Arris.Toolkit.FileUpload (karelwintersky/arris.php-file-upload)

Technical reference for the agent. Work with this package without digging through the repository files.

## Overview

- Repository: `/var/www.arris/Arris.Toolkit.FileUpload/`
- Package: `karelwintersky/arris.php-file-upload`, latest version `0.3.0` (from git commit messages; no `version` field in composer.json, **no git tags**).
- Purpose: HTTP file upload library with two-phase API (`uploaded()` → `process()`), built-in + custom validation, GD image conversion, a backed-enum error-code system with an i18n translator, and an `ffprobe` media metadata wrapper.
- MIT license, `type: library`, PHP `^8.2`. Extensions: ext-fileinfo (required), ext-gd (required for conversion, tests skip if absent).

## composer.json (facts)

- `autoload` PSR-4: **single mapping** `Arris\Toolkit\ → sources/`. There is NO separate `Arris\Toolkit\FileUpload\` mapping — the `sources/FileUpload/` subdirectory resolves through the same prefix.
- `require`: `php ^8.2`, `ext-fileinfo`, `ext-gd`
- `require-dev`: `phpunit/phpunit ^10.5` (installed: 10.5.64)
- `scripts`: `test` = `phpunit`, `test-coverage` = `phpunit --coverage-html coverage`
- No `repositories` block (no local path stubs in THIS repo).
- Not published to Packagist (published-variant install string exists in README only).

## Structure

```
sources/
  FileUpload.php                  # main class (Arris\Toolkit) — two-phase upload API
  FileUploadResult.php            # immutable readonly value-object of result
  FileUploadException.php         # custom exception (carries errors array)
  FileUpload/
    ErrorCode.php                 # backed string enum, 18 cases (NOT FileUploadErrorCode)
    ErrorMessages.php             # i18n translator codes → strings (NOT FileUploadErrorMessages)
    Helper.php                    # static: size-string→bytes, php.ini limits
    ImageConvertor.php            # GD converter, fluent API
    MediaProbe.php                # ffprobe wrapper, returns MediaProbeResult|false
    MediaProbeResult.php          # immutable readonly value-object of probe
tests/
  FileUploadTest.php              # 31 tests (main class)
  FileUploadErrorMessagesTest.php # 15 tests (translator + i18n)
  FileUploadHelperTest.php        # 14 tests
  FileUploadResultTest.php        # 7 tests
  FileUploadExceptionTest.php     # 4 tests
  ImageConvertorTest.php          # 9 tests (skips if ext-gd missing)
  MediaProbeTest.php              # 7 tests
demo/
  index.php                       # web demo (front + backend in one file)
  uploads/                        # target dir for uploaded files
phpunit.xml                       # bootstrap=vendor/autoload.php, testsuite tests/
```

Note: only `tests/FileUploadHelperTest.php` is tracked in git; the other test files are currently untracked (repo hygiene, not code).

## Key classes and API

### `Arris\Toolkit\FileUpload` (main entry)

Two-phase flow:
- `uploaded(): FileUploadResult` — checks `is_uploaded_file()` + PHP upload error + full validation (MIME, size, custom validators). On success sets internal `$validated = true` and caches the result (`$uploadedResult`). Returns stage=`uploaded`, fills `mimeType`/`size`/`width`/`height`/`tmpName`/`relativePath`.
- `process(): FileUploadResult` — obtains the source descriptor via private `ensureUploadedResult()` (replays cached `uploaded()` or, if only `validate()` ran, rebuilds it from `$validated`), then creates `targetPath` dir (0755 recursive), generates filename, optional image conversion, `move_uploaded_file()`. Returns stage=`processed` with `savedName`/`path`/`fullPath`/`radix`/`extension`.
- `is_uploaded(): FileUploadResult` — alias of `uploaded()`.

`filenameGenerator` is a callable receiving ONE argument — the source `FileUploadResult` (stage `uploaded`). Full mechanics — see «filenameGenerator — механизм» below.

Static:
- `fromFile(array $file, ?int $index = null): self`
- `setDefaultConfig(array $config): void`
- `applyOption(string $name, mixed $value): void` — throws `InvalidArgumentException` on unknown option.

Fluent setters (return `self`): `setTargetPath(string)`, `allowMimeTypes(array)`, `setMaxFileSize(int)` (0 → PHP_INT_MAX), `setMinFileSize(int)`, `addValidator(callable)`, `setFilenameGenerator(callable)`, `throwExceptions(bool)`, `setLocale(string)`, `setTargetMimeType(string $mime, int $quality = 90, bool $force = false)`.

Validation: `validate(): bool`. Preconditions fail-fast (no file / not uploaded / upload error), then collect-all (MIME → min size → max size → custom validators). Custom validator returns `true` (pass) / `false` (default message) / `string` (message used via `{message}` param).

Accessors: `getErrors(): array` (resolved strings), `getErrorStack(): array` (raw `['code'=>ErrorCode,'params'=>[...]]`), `getFile(): array`, `getFileIndex(): ?int`, `withFile(array, ?int): self` (clone, resets `validated`/`errorStack`/`uploadedResult`/`detectedMimeType`).

Options: `targetPath`, `allowedMimeTypes`, `maxFileSize`, `minFileSize`, `filenameGenerator`, `throwExceptions`, `validators`, `targetMimeType`, `targetImageQuality`, `locale`. Default locale is `'ru'`.

`process()` catches `\Throwable` (not just `\Exception`) — to catch `ValueError` from `mime_content_type()` on an empty path. In throw mode rethrows `FileUploadException` with resolved errors; in silent mode returns a failed `FileUploadResult`.

### `filenameGenerator` — механизм

**Сигнатура (breaking change 2026-09-18)**: `fn(FileUploadResult $source): string`, раньше было `fn(string $originalName, array $file): string`. `$source` — дескриптор исходного файла стадии `uploaded`:

- `mimeType` — детектирован из `tmp_name` (не из `$_FILES[*]['type']` — тот приходит от браузера и недоверяем), одноразовый ленивый `detectMimeType()` с кэшем на инстанс.
- `tmpName` — `$_FILES[*]['tmp_name']`.
- `relativePath` — `$_FILES[*]['full_path']`; для одиночной загрузки PHP даёт полное имя файла, а библиотека фолбэчит на `originalName`; при загрузке целой папки — относительный путь.
- `originalName`, `width`, `height`, `size` — как в `uploaded()`.

**Где вызывается**: в `process()` строго до `move_uploaded_file()` и до конверсии. Источник дескриптора — `ensureUploadedResult()`: 1) кэш `$uploadedResult` → 2) если `$validated` (был только `validate()` без `uploaded()`) — дескриптор собирается из файла без повторной валидации (mime из кэша детекта, размерности — `getImageDimensions`) → 3) иначе запускается `uploaded()`. Результат кэшируется.

**Конверсия**: итоговое расширение переопределяет `changeExtension()` по `targetMimeType` (напр. `image/jpeg`→`.jpg`), генератору не нужно знать целевой формат.

**Дефолт без генератора**: исходное имя; при коллизии в `targetPath` — `name_1.ext`, `name_2.ext`, … (по `file_exists`).

**Правила употребления**:
- возвращать только имя файла, без `targetPath`;
- значение НЕ санитизируется — нормализация имени (от `../`, `/`, спецсимволов) — ответственность генератора;
- в генераторе не перечитывать файл (`mime_content_type`, `getimagesize`) — все данные уже в `$source`;
- типовой кейс «расширение по содержимому, фолбэк на имя» — см. README (раздел «Генератор имени файла») и `demo/index.php`.

### `Arris\Toolkit\FileUploadResult`

Readonly value-object, promoted constructor params:
`isSuccess: bool, stage, originalName, savedName, path, fullPath, mimeType, size: ?int, lastError, errors: array, radix, extension, width: ?int, height: ?int, tmpName: ?string, relativePath: ?string` (all nullable where not bool/array). `tmpName` = `$_FILES['tmp_name']`, `relativePath` = `$_FILES['full_path']` (falls back to `originalName`) — both filled on the `uploaded` stage. Constants `STAGE_UPLOADED = 'uploaded'`, `STAGE_PROCESSED = 'processed'`.

Methods: `toJson(bool $pretty = false): string`, `toArray(): array`, `__toString(): string` (JSON).

### `Arris\Toolkit\FileUploadException extends \Exception`

`__construct(string $message = "", array $errors = [], int $code = 0, ?Throwable $previous = null)`, `getErrors(): array`.

### `Arris\Toolkit\FileUpload\ErrorCode` (enum, string-backed, 18 cases)

`FILE_NOT_SET`, `NOT_UPLOADED`, `UPLOAD_ERR_INI_SIZE`, `UPLOAD_ERR_FORM_SIZE`, `UPLOAD_ERR_PARTIAL`, `UPLOAD_ERR_NO_FILE`, `UPLOAD_ERR_NO_TMP_DIR`, `UPLOAD_ERR_CANT_WRITE`, `UPLOAD_ERR_EXTENSION`, `INVALID_MIME_TYPE`, `FILE_TOO_SMALL`, `FILE_TOO_LARGE`, `VALIDATOR_FAILED`, `TARGET_PATH_NOT_SET`, `TARGET_DIR_CREATE_FAILED`, `FILE_MOVE_FAILED`, `CONVERSION_FAILED`, `EXCEPTION`. PHP `UPLOAD_ERR_*` → enum via private `mapUploadErrorCode()`.

### `Arris\Toolkit\FileUpload\ErrorMessages` (static translator)

Static state: `$locale` (default `'ru'`) + `$overrides`. Built-in locales: `ru`, `en` (+ fallback `'_'`). Params in templates: `{mime_type}`, `{message}`.

- `resolve(ErrorCode $code, array $params = []): string` — lookup order: override → current locale → `'_'` → `$code->value`
- `setLocale(string)`, `getLocale(): string`, `setMessage(ErrorCode, string)`, `setMessages(array)`, `reset(): void` (back to `ru`, clear overrides)

### `Arris\Toolkit\FileUpload\Helper` (static)

- `returnBytes(string|int|float $val): int` — `"64M"`, `"1G"`, `"1024K"`, floats → bytes; invalid → 0
- `getIniValue(string $key): int` — php.ini directive as bytes
- `getUploadLimits(string $applicationMaxSize = '64M'): array` — keys `POST_MAX_SIZE`, `UPLOAD_MAX_SIZE`, `CONFIG_MAX_SIZE`, `REAL_MAX_SIZE` (min of three), `IS_WRONG_SIZE`

### `Arris\Toolkit\FileUpload\ImageConvertor`

Constructor takes `string $sourcePath`. Fluent: `setTargetFormat(string $mime): self`, `setQuality(int $q 0-100): self` (PNG → compression level 0-9), `convert(string $targetPath): bool`.

Supported source MIME (via `mime_content_type`): jpeg/jpg, png, gif, webp, x-ms-bmp. Target: jpeg/jpg, png, gif, webp. Alpha channel preserved for PNG/GIF output. Returns `false` (not exceptions) if GD missing, source not a file, or unsupported format. **NOTE:** README shows a `ImageConvertor::from()->toWebP()->save()` fluent API — this does NOT exist in the code.

### `Arris\Toolkit\FileUpload\MediaProbe` + `MediaProbeResult`

`MediaProbe::probe(string $filepath): MediaProbeResult|false` — runs `ffprobe -v quiet -print_format json -show_format -show_streams <file>`, returns `false` on missing file / non-zero exit / decode error. Requires `ffprobe` binary in PATH; throws `\JsonException` only on malformed JSON.

`MediaProbeResult` readonly props: `duration: ?float`, `size: ?int`, `bitrate: ?int`, `format: ?string`, `width/height: ?int`, `videoCodec/videoBitrate/pixelFormat: ?string/?int/?string`, `fps: ?float`, `audioCodec/audioBitrate/sampleRate/channels/channelLayout`. FPS parsed from fraction string (`"30000/1001"`), rounded to 2 decimals.

## Global functions

None. No `autoload.files`; everything is class-based.

## Tests

```bash
composer install
vendor/bin/phpunit                      # or: make test, or: composer test
vendor/bin/phpunit --testdox
```

Current status: **89 tests, 318 assertions — all pass** (PHPUnit 10.5.64, PHP 8.2.30). Per file: FileUpload 33, FileUploadErrorMessages 15, FileUploadHelper 14, FileUploadResult 7, MediaProbe 7, ImageConvertor 9, FileUploadException 4. ImageConvertor tests skip if ext-gd is not loaded.

## Wiring into a project

- Published: `composer require karelwintersky/arris.php-file-upload`
- Local (dev of the Arris ecosystem): add a path repository pointing at `/var/www.arris/Arris.Toolkit.FileUpload/` in the consumer's `composer.json`. A sibling consumer exists: `/var/www.arris/Arris.Entity.File.Upload-0/`.

## Status / TODO / integrations

- Companion Arris packages: `karelwintersky/arris.entity.file` (`/var/www.arris/Arris.Entity.File/`), `karelwintersky/arris.php-file-download` (`/var/www.arris/Arris.Toolkit.FileDownload/`). The full package→directory map lives in the global `~/.config/opencode/AGENTS.md`.
- README has drifted from the code in places: mentions `FileUploadErrorCode`/`FileUploadErrorMessages` class names (actual: `ErrorCode`/`ErrorMessages`), `ImageConvertor::from()->toWebP()` fluent API (actual: constructor + `setTargetFormat`), and `MediaProbe::probe` returning `null` (actual: `false`). Trust the source, not the README.

---

## History (changelog)

### Generator rework + source descriptor (2026-09-18)

**Breaking change**: `filenameGenerator` signature changed from `fn(string $originalName, array $file): string` to `fn(FileUploadResult $source): string` — the generator now receives one `FileUploadResult` (stage `uploaded`) describing the source.

Motivation (bug in dch_pulsar_agent web form): a JPEG uploaded under a `.png` name was saved to disk with the client-filename extension `.png` while the DB recorded `image/jpeg`/`.jpg` (extension derived from content MIME), breaking file/DB consistency. The generator now has access to the real content MIME (`mimeType`, computed from `tmp_name`), the client path (`relativePath` = `$_FILES['full_path']`, fallback `originalName`) and the temp path (`tmpName`).

Implementation notes:
- `FileUploadResult` gained `tmpName: ?string` and `relativePath: ?string` (readonly, appended after `height`); included in `toArray()`/`toJson()`.
- `FileUpload` caches the successful `uploaded()` result (`$uploadedResult`); `process()` uses private `ensureUploadedResult()` — replays the cache, or if only `validate()` ran (no `uploaded()`), rebuilds the descriptor from the validated file, otherwise runs `uploaded()`. The cached result is passed to the generator. Critical: `process()` with an already-validated file still tries `move_uploaded_file()` (error must be `FILE_MOVE_FAILED`, not validation).
- MIME detection is lazy and cached per instance (`detectMimeType()`) — `mime_content_type()` runs at most once per file (was up to 3×). `getimagesize()` is not re-run on the non-conversion path of `process()` (reuses source dimensions).
- `withFile()` resets `uploadedResult` + `detectedMimeType` alongside `validated`/`errorStack`.
- Tests: +2 methods in `FileUploadTest` (`testFilenameGeneratorReceivesSourceResult`, `testWithFileResetsUploadCaches`), extended `FileUploadResultTest` for the new fields. 87/295 → 89/318.
- Consumers updated: dch_pulsar_agent `app/Controllers/MainController.php` (now derives extension from `$source->mimeType` via `MimeTypes::fromType()` with `pathinfo` fallback), library `demo/index.php`, `README.md`. Vendored copies in dch_pulsar and dch_pulsar_agent resynced.

### AGENTS.md rewrite (2026-08-07)

The previous AGENTS.md was an untracked draft (never committed; identical copy in `AGENTS.bak`) containing stale facts: wrong class names (`FileUploadErrorCode`/`FileUploadErrorMessages`), a non-existent two-entry PSR-4 map, a missing `Helper` class/test, and outdated test counts (73/249). Rewritten into this reference from a real test run (87 tests / 295 assertions) and source inspection.

### Package history (from git commits, 0.0.1 → 0.3.0)

`0.0.1` → `0.0.2` → `0.0.3` → `0.1.0` → `0.1.1` → `0.1.2` → `0.1.3` → `0.1.4` → `0.1.5` → `0.1.6` → `0.1.7` → `0.1.8` → `0.1.9` → `0.1.10` → `0.1.11` → `0.2.0` → `0.3.0` (HEAD). Initial commit is pre-0.0.1.
