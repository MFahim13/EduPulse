<?php
require __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$type = (string) ($_GET['type'] ?? '');
$id = (int) ($_GET['id'] ?? 0);

$file = match ($type) {
    'material' => query(
        'SELECT m.file_path, m.file_name AS download_name, mo.course_id, NULL AS student_id
         FROM materials m JOIN modules mo ON mo.id = m.module_id WHERE m.id = ?',
        [$id]
    )->fetch(),
    'assignment' => query(
        'SELECT a.file_path, a.title AS download_name, a.course_id, NULL AS student_id
         FROM assignments a WHERE a.id = ? AND a.file_path IS NOT NULL',
        [$id]
    )->fetch(),
    'submission' => query(
        "SELECT s.file_path, CONCAT(u.name, ' - ', a.title) AS download_name, a.course_id, s.student_id
         FROM submissions s
         JOIN assignments a ON a.id = s.assignment_id
         JOIN users u ON u.id = s.student_id
         WHERE s.id = ?",
        [$id]
    )->fetch(),
    default => false,
};

if (!$file) {
    render_error_page(404, 'File not found', "That file doesn't exist or has been removed.");
}

$allowed = $type === 'submission'
    ? owns_course($user['id'], (int) $file['course_id']) || (int) $file['student_id'] === $user['id']
    : can_view_course_content($user, (int) $file['course_id']);

if (!$allowed) {
    render_error_page(403, 'Access denied', "You don't have access to this file.");
}

$path = upload_path((string) $file['file_path']);
if (!is_file($path)) {
    render_error_page(404, 'File not found', "That file doesn't exist or has been removed.");
}

$name = (string) $file['download_name'];
$ext = strtolower(pathinfo((string) $file['file_path'], PATHINFO_EXTENSION));
if ($type !== 'material' && $ext !== '') {
    $name .= '.' . $ext;
}
$ascii = preg_replace('/[^A-Za-z0-9 ._-]/', '_', $name);

header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Cache-Control: private, no-store');
readfile($path);
