<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'course_enrollment';

function db(): mysqli
{
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function clean_input(?string $value): string
{
    return trim((string) $value);
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function require_auth(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

function random_file_name(string $originalName): string
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $base = bin2hex(random_bytes(16));
    return $base . ($extension ? '.' . $extension : '');
}

function sanitize_original_name(string $name): string
{
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'file';
    return preg_replace('/_+/', '_', $name) ?? 'file';
}

function remove_local_file(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\\\'], DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function upload_file(array $file, string $targetDirRelative, array $allowedExtensions, int $maxBytes, array &$errors, string $label): ?array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = $label . ' is required.';
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = $label . ' upload failed.';
        return null;
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        $errors[] = $label . ' has an invalid file type.';
        return null;
    }

    if ((int) $file['size'] > $maxBytes) {
        $errors[] = $label . ' exceeds allowed size.';
        return null;
    }

    $baseDir = dirname(__DIR__);
    $targetDir = $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\\\'], DIRECTORY_SEPARATOR, $targetDirRelative);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        $errors[] = 'Could not prepare upload directory.';
        return null;
    }

    $storedName = random_file_name($originalName);
    $destination = $targetDir . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
        $errors[] = $label . ' could not be saved.';
        return null;
    }

    return [
        'path' => rtrim($targetDirRelative, '/\\') . '/' . $storedName,
        'original_name' => sanitize_original_name($originalName),
        'stored_name' => $storedName,
        'size' => (int) $file['size'],
        'mime_type' => mime_content_type($destination) ?: 'application/octet-stream',
    ];
}

function upload_file_optional(array $file, string $targetDirRelative, array $allowedExtensions, int $maxBytes, array &$errors, string $label): ?array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    return upload_file($file, $targetDirRelative, $allowedExtensions, $maxBytes, $errors, $label);
}