<?php

/**
 * Demo-скрипт для Arris\Toolkit\FileUpload
 *
 * Запуск:  php -S localhost:8080 -t demo/ demo/index.php
 * Открыть: http://localhost:8080/
 *
 * Обрабатывает GET (отдача HTML) и POST /upload (загрузка файлов).
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Arris\Toolkit\FileUpload;
use Arris\Toolkit\FileUploadResult;

// ─── Роутинг ────────────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'POST' && $uri === '/upload') {
    handleUpload();
    exit;
}

// ─── Главная страница ───────────────────────────────────────────────────────

header('Content-Type: text/html; charset=utf-8');
echo <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>FileUpload Demo</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Consolas,"Courier New",monospace;background:#1a1a2e;color:#e0e0e0;padding:20px}
h1{color:#0f3460;background:#e94560;padding:10px 16px;border-radius:6px;margin-bottom:16px;font-size:18px}
.controls{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
.controls label{display:flex;align-items:center;gap:6px;padding:8px 14px;background:#16213e;border:1px solid #0f3460;border-radius:4px;cursor:pointer;transition:.2s}
.controls label:hover{border-color:#e94560}
.controls input[type=file]{display:none}
.controls input[type=text]{background:#16213e;border:1px solid #0f3460;color:#e0e0e0;padding:6px 10px;border-radius:4px;width:200px}
.btn{padding:8px 20px;background:#e94560;color:#fff;border:none;border-radius:4px;cursor:pointer;font-family:inherit;font-size:14px}
.btn:hover{background:#c73650}
.btn:disabled{opacity:.4;cursor:default}
#console{background:#0d1117;border:1px solid #30363d;border-radius:6px;padding:12px;max-height:70vh;overflow-y:auto;font-size:13px;line-height:1.5}
.log{margin-bottom:4px;white-space:pre-wrap;word-break:break-all}
.log.info{color:#58a6ff}
.log.ok{color:#3fb950}
.log.err{color:#f85149}
.log.warn{color:#d29922}
.log.dump{color:#bc8cff}
.sep{border-top:1px dashed #30363d;margin:8px 0}
</style>
</head>
<body>
<h1>Arris Toolkit FileUpload — Demo</h1>

<div class="controls">
  <label>Выбрать файлы
    <input type="file" id="fileInput" multiple>
  </label>
  <span id="fileNames" style="color:#888">не выбрано</span>
  <input type="text" id="targetMime" placeholder="target MIME (напр. image/webp)">
  <button class="btn" id="uploadBtn" disabled>Загрузить</button>
  <button class="btn" style="background:#30363d" onclick="clearLog()">Очистить</button>
</div>

<div id="console"></div>

<script>
const fileInput  = document.getElementById('fileInput');
const uploadBtn  = document.getElementById('uploadBtn');
const fileNames  = document.getElementById('fileNames');
const targetMime = document.getElementById('targetMime');
const con        = document.getElementById('console');

fileInput.addEventListener('change', () => {
  const n = fileInput.files.length;
  fileNames.textContent = n ? n + ' файл(ов): ' + Array.from(fileInput.files).map(f=>f.name).join(', ') : 'не выбрано';
  uploadBtn.disabled = n === 0;
});

uploadBtn.addEventListener('click', doUpload);

function clearLog() { con.innerHTML = ''; }

function log(text, cls = '') {
  const d = document.createElement('div');
  d.className = 'log ' + cls;
  d.textContent = text;
  con.appendChild(d);
  con.scrollTop = con.scrollHeight;
}

function sep() {
  const d = document.createElement('div');
  d.className = 'sep';
  con.appendChild(d);
}

function fmtSize(b) {
  if (b < 1024) return b + ' B';
  if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
  return (b/1048576).toFixed(1) + ' MB';
}

async function doUpload() {
  const files = fileInput.files;
  if (!files.length) return;

  uploadBtn.disabled = true;
  const tm = targetMime.value.trim();

  for (let i = 0; i < files.length; i++) {
    const file = files[i];
    sep();
    log('>>> Файл: ' + file.name + '  (' + fmtSize(file.size) + ')', 'info');

    // Этап 1: локальная проверка (то же, что uploaded() на сервере)
    log('    [client] Формируем FormData, отправляем POST /upload ...');

    const fd = new FormData();
    fd.append('file', file);
    if (tm) fd.append('target_mime', tm);

    const t0 = performance.now();
    try {
      const resp = await fetch('/upload', { method: 'POST', body: fd });
      const data = await resp.json();
      const elapsed = ((performance.now() - t0) / 1000).toFixed(2);

      if (data.error) {
        log('    [server] ОШИБКА: ' + data.error, 'err');
        if (data.trace) log(data.trace, 'err');
        continue;
      }

      // Показываем uploaded()
      log('    [uploaded]    stage: ' + data.uploaded.stage, data.uploaded.isSuccess ? 'ok' : 'err');
      log('    [uploaded]    FileUploadResult dump:', 'dump');
      log(JSON.stringify(data.uploaded, null, 2).split('\\n').map(l=>'      '+l).join('\\n'), 'dump');

      // Показываем process()
      log('    [processed]   stage: ' + data.processed.stage, data.processed.isSuccess ? 'ok' : 'err');
      log('    [processed]   FileUploadResult dump:', 'dump');
      log(JSON.stringify(data.processed, null, 2).split('\\n').map(l=>'      '+l).join('\\n'), 'dump');

      log('    Время ответа: ' + elapsed + 's', 'info');

    } catch (e) {
      log('    [network] ОШИБКА: ' + e.message, 'err');
    }
  }
  sep();
  log('=== Готово. Загружено файлов: ' + files.length, 'info');
  uploadBtn.disabled = false;
}
</script>
</body>
</html>
HTML;
exit;


// ─── Обработка загрузки ─────────────────────────────────────────────────────

function handleUpload(): void
{
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ожидается POST с полем file']);
        return;
    }

    $uploadDir = __DIR__ . '/uploads';

    // Конфигурация по умолчанию
    FileUpload::setDefaultConfig([
        'targetPath'       => $uploadDir . '/',
        'allowedMimeTypes' => [/*'image/jpeg',*/ 'image/png', 'image/gif', /*'image/webp',*/ 'text/plain', 'application/pdf'],
        'maxFileSize'      => 10 * 1024 * 1024,
        'throwExceptions'  => false,
    ]);

    $file = $_FILES['file'];
    $results = [];

    try {
        $uploader = FileUpload::fromFile($file)
            ->addValidator(function (array $file) {
                if ($file['size'] < 300*1024) {
                    return 'Файл слишком маленький (минимум 300KB)';
                }
                return true;
            });

    } catch (\Throwable $e) {
        echo json_encode(['error' => 'Не удалось создать FileUpload: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        return;
    }

    // ── Этап 1: uploaded() — лёгкая проверка ──
    try {
        $uploadResult = $uploader->uploaded();
    } catch (\Throwable $e) {
        echo json_encode(['error' => 'uploaded() выбросил исключение: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        return;
    }

    $results['uploaded'] = $uploadResult->toArray();

    if (!$uploadResult->isSuccess) {
        echo json_encode($results);
        return;
    }

    // ── Этап 2: process() — валидация + перемещение ──
    try {
        $processResult = $uploader->process();
    } catch (\Throwable $e) {
        echo json_encode(['error' => 'process() выбросил исключение: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        return;
    }

    $results['processed'] = $processResult->toArray();

    echo json_encode($results);
}
