<?php

namespace Arris\Toolkit;

enum FileUploadErrorCode: string
{
    // Файл не передан / не существует
    case FILE_NOT_SET = 'file_not_set';

    // PHP-уровень: is_uploaded_file() вернул false
    case NOT_UPLOADED = 'not_uploaded';

    // PHP upload-ошибки (соответствуют UPLOAD_ERR_*)
    case UPLOAD_ERR_INI_SIZE = 'upload_err_ini_size';
    case UPLOAD_ERR_FORM_SIZE = 'upload_err_form_size';
    case UPLOAD_ERR_PARTIAL = 'upload_err_partial';
    case UPLOAD_ERR_NO_FILE = 'upload_err_no_file';
    case UPLOAD_ERR_NO_TMP_DIR = 'upload_err_no_tmp_dir';
    case UPLOAD_ERR_CANT_WRITE = 'upload_err_cant_write';
    case UPLOAD_ERR_EXTENSION = 'upload_err_extension';

    // Валидация
    case INVALID_MIME_TYPE = 'invalid_mime_type';
    case FILE_TOO_SMALL = 'file_too_small';
    case FILE_TOO_LARGE = 'file_too_large';
    case VALIDATOR_FAILED = 'validator_failed';

    // Файловые операции (process)
    case TARGET_PATH_NOT_SET = 'target_path_not_set';
    case TARGET_DIR_CREATE_FAILED = 'target_dir_create_failed';
    case FILE_MOVE_FAILED = 'file_move_failed';
    case CONVERSION_FAILED = 'conversion_failed';

    // Исключение
    case EXCEPTION = 'exception';
}
