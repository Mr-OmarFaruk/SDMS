<?php
declare(strict_types=1);

session_start();

// Read database settings from environment variables or config.local.php.
function config(string $key, ?string $default = null): ?string
{
    static $config;

    if ($config === null) {
        $local = is_file(__DIR__ . '/../config.local.php') ? require __DIR__ . '/../config.local.php' : [];

        $config = [
            'db_host' => getenv('DB_HOST') !== false ? getenv('DB_HOST') : ($local['db_host'] ?? '127.0.0.1'),
            'db_port' => getenv('DB_PORT') !== false ? getenv('DB_PORT') : ($local['db_port'] ?? '3306'),
            'db_name' => getenv('DB_DATABASE') !== false ? getenv('DB_DATABASE') : ($local['db_name'] ?? 'sdms'),
            'db_user' => getenv('DB_USERNAME') !== false ? getenv('DB_USERNAME') : ($local['db_user'] ?? 'root'),
            'db_pass' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($local['db_pass'] ?? ''),
        ];
    }

    return $config[$key] ?? $default;
}

// Load the PDO helper after config() is defined because it uses these settings.
require_once __DIR__ . '/../config/db.php';

function url(string $page = 'dashboard', array $params = []): string
{
    return 'index.php?' . http_build_query(array_merge(['page' => $page], $params));
}

function redirect(string $page, array $params = []): never
{
    header('Location: ' . url($page, $params));
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Invalid or expired form token.');
    }
}

function user(): ?array
{
    if (array_key_exists('current', $_SESSION)) {
        return $_SESSION['current'];
    }

    $current = null;
    if (!empty($_SESSION['user_id'])) {
        $statement = db()->prepare('SELECT * FROM users WHERE id = ?');
        $statement->execute([$_SESSION['user_id']]);
        $current = $statement->fetch() ?: null;
    }
    $_SESSION['current'] = $current;
    return $current;
}

function require_login(): array
{
    $current = user();
    if (!$current) {
        redirect('login');
    }

    return $current;
}

function require_role(string ...$roles): array
{
    $current = require_login();
    if (!in_array($current['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }
    return $current;
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }

    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}

function form_errors(): array
{
    return $_SESSION['errors'] ?? [];
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

function remember_form(array $data): void
{
    $_SESSION['old'] = $data;
    $_SESSION['errors'] = [];
}

function fail_form(array $errors, string $page, array $params = []): never
{
    $_SESSION['errors'] = $errors;
    redirect($page, $params);
}

function clear_form(): void
{
    unset($_SESSION['old'], $_SESSION['errors']);
}

function status_class(string $status): string
{
    return ['pending' => 'warning', 'accepted' => 'success', 'rejected' => 'danger'][$status] ?? 'secondary';
}

function format_date(?string $date): string
{
    return $date ? date('M j, Y g:i A', strtotime($date)) : '-';
}

function application(int $id): ?array
{
    $statement = db()->prepare('SELECT a.*, s.name AS student_name, s.email AS student_email, t.name AS teacher_name, t.email AS teacher_email FROM applications a JOIN users s ON s.id = a.student_id LEFT JOIN users t ON t.id = a.teacher_id WHERE a.id = ?');
    $statement->execute([$id]);
    return $statement->fetch() ?: null;
}

function can_access_application(array $application, array $current): bool
{
    return $current['role'] === 'admin'
        || (int) $application['student_id'] === (int) $current['id']
        || (int) $application['teacher_id'] === (int) $current['id'];
}

function upload_document(string $field): ?string
{
    if (empty($_FILES[$field]['name'])) {
        return null;
    }

    $file = $_FILES[$field];
    $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5242880 || !in_array($extension, $allowed, true)) {
        return null;
    }

    $directory = __DIR__ . '/../storage/documents';

    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $name = bin2hex(random_bytes(12)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) {
        return null;
    }

    return 'documents/' . $name;
}