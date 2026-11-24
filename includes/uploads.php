<?php
declare(strict_types=1);

/** A user-facing upload problem; the message is safe to show. */
final class UploadError extends RuntimeException
{
}

/** MIME types finfo may report for each allowed extension. */
const UPLOAD_MIME_TYPES = [
    'pdf' => ['application/pdf'],
    'doc' => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    'txt' => ['text/plain'],
    'zip' => ['application/zip', 'application/x-zip-compressed'],
    'png' => ['image/png'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
];

/** @return array{exts: string[], max_bytes: int, private: bool} */
function upload_rules(string $purpose): array
{
    $mb = 1024 * 1024;
    return match ($purpose) {
        'material' => ['exts' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'png', 'jpg', 'jpeg'], 'max_bytes' => 10 * $mb, 'private' => true],
        'assignment' => ['exts' => ['pdf', 'doc', 'docx', 'txt', 'zip'], 'max_bytes' => 10 * $mb, 'private' => true],
        'submission' => ['exts' => ['pdf', 'doc', 'docx', 'zip'], 'max_bytes' => 10 * $mb, 'private' => true],
        'profile' => ['exts' => ['jpg', 'jpeg', 'png'], 'max_bytes' => 2 * $mb, 'private' => false],
        default => throw new InvalidArgumentException("Unknown upload purpose: {$purpose}"),
    };
}

/** Validate one entry from $_FILES and return its lower-cased extension. */
function validate_upload(array $file, string $purpose): string
{
    $rules = upload_rules($purpose);
    $maxMb = intdiv($rules['max_bytes'], 1024 * 1024);
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new UploadError('Please choose a file to upload.');
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new UploadError("File too large (max {$maxMb} MB).");
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new UploadError('The upload failed. Please try again.');
    }

    $name = upload_original_name((string) ($file['name'] ?? ''));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === '') {
        throw new UploadError('Files need an extension such as .pdf.');
    }
    if (!in_array($ext, $rules['exts'], true)) {
        throw new UploadError('That file type is not allowed. Allowed types: ' . strtoupper(implode(', ', $rules['exts'])) . '.');
    }
    if ((int) ($file['size'] ?? 0) > $rules['max_bytes'] || filesize($file['tmp_name']) > $rules['max_bytes']) {
        throw new UploadError("File too large (max {$maxMb} MB).");
    }

    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, UPLOAD_MIME_TYPES[$ext], true)) {
        if ($purpose === 'profile') {
            throw new UploadError('Please upload a real JPG or PNG image.');
        }
        throw new UploadError("The file's contents don't match its .{$ext} extension.");
    }
    if ($purpose === 'profile' && @getimagesize($file['tmp_name']) === false) {
        throw new UploadError('Please upload a real JPG or PNG image.');
    }

    return $ext;
}

/**
 * Validate and move an upload to storage under a random name.
 * @return array{key: string, original: string, mime: string}
 */
function store_upload(array $file, string $purpose): array
{
    $ext = validate_upload($file, $purpose);
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $key = upload_rules($purpose)['private']
        ? $purpose . 's/' . $name
        : 'assets/uploads/profiles/' . $name;

    $destination = upload_path($key);
    if (!is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0775, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new UploadError('The upload failed. Please try again.');
    }
    chmod($destination, 0644);

    return [
        'key' => $key,
        'original' => upload_original_name((string) $file['name']),
        'mime' => (string) (new finfo(FILEINFO_MIME_TYPE))->file($destination),
    ];
}

/** Absolute path for a storage key; public profile keys live under public/. */
function upload_path(string $key): string
{
    if (str_starts_with($key, 'assets/uploads/profiles/')) {
        return PROJECT_ROOT . '/public/' . $key;
    }
    return PROJECT_ROOT . '/storage/uploads/' . $key;
}

function delete_upload(?string $key): void
{
    if ($key === null || $key === '' || str_contains($key, '..') || str_contains($key, "\0")) {
        return;
    }
    $path = upload_path($key);
    if (is_file($path)) {
        unlink($path);
    }
}

/** The browser-supplied file name, reduced to a safe display string. */
function upload_original_name(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
    return mb_substr(trim($name), 0, 255);
}

/**
 * Normalise $_FILES['field'] for a multi-file input into a list of single files.
 * @return list<array>
 */
function uploaded_files(string $field): array
{
    $files = $_FILES[$field] ?? null;
    if (!is_array($files) || !is_array($files['name'] ?? null)) {
        return [];
    }
    $list = [];
    foreach ($files['name'] as $i => $name) {
        if ((int) $files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $list[] = [
            'name' => $name,
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];
    }
    return $list;
}

/** True when a POST was dropped by PHP because it exceeded post_max_size. */
function post_too_large(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $_POST !== [] || $_FILES !== []) {
        return false;
    }
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    return $length > 0 && $length > ini_bytes((string) ini_get('post_max_size'));
}

function ini_bytes(string $value): int
{
    $value = trim($value);
    $number = (int) $value;
    return match (strtolower(substr($value, -1))) {
        'g' => $number * 1024 ** 3,
        'm' => $number * 1024 ** 2,
        'k' => $number * 1024,
        default => $number,
    };
}
